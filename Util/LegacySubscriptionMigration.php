<?php

namespace Plugin\UnivaPay\Util;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Master\OrderItemType;
use Eccube\Entity\Order;
use Eccube\Entity\Payment;
use Eccube\Service\PurchaseFlow\Processor\OrderNoProcessor;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Plugin\UnivaPay\Entity\Config;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;
use Plugin\UnivaPay\Service\Method\CreditCard;
use UnivaPay\Models\SubscriptionStatus;

/**
 * Migrates 1.5.0 subscription orders. Runs from PluginManager::update() while the 1.5.0 plugin classes are still
 * loaded, so order data goes through DBAL rows and only core entities go through the ORM.
 */
class LegacySubscriptionMigration
{
    /**
     * Only installs up to 1.5.0 have a dtb_payment row with this (now deleted) class.
     */
    const PAYMENT_METHOD_CLASS = 'Plugin\UnivaPay\Service\Method\Subscription';

    public static function isRequired(EntityManagerInterface $entityManager)
    {
        return $entityManager->getRepository(Payment::class)->findOneBy(['method_class' => self::PAYMENT_METHOD_CLASS]) !== null;
    }

    private static function apiStatusFetcher(EntityManagerInterface $entityManager)
    {
        $config = $entityManager->find(Config::class, 1);
        if (!$config || !$config->getAppId() || !$config->getAppSecret()) {
            UnivaPayLogger::get()->warning('[UnivaPay] API credentials not set, legacy subscriptions migrated as active');

            return function () {
                return null;
            };
        }

        $sdk = null;

        return function ($subscriptionId) use ($config, &$sdk) {
            if (!$sdk) {
                $sdk = new SDK($config);
            }

            return $sdk->getSubscription($subscriptionId)->getStatus();
        };
    }

    /**
     * 1.5.0 used the first order as the parent, so this creates a new parent order without a charge id per subscription
     * and copies univa_pay_parent_subscription_id to every existing order. Existing charge ids and statuses are not changed.
     */
    public static function run(EntityManagerInterface $entityManager, OrderNoProcessor $orderNoProcessor, ?callable $fetchSubscriptionStatus = null)
    {
        $connection = $entityManager->getConnection();
        $paymentRepository = $entityManager->getRepository(Payment::class);
        $legacyPayment = $paymentRepository->findOneBy(['method_class' => self::PAYMENT_METHOD_CLASS]);
        if (!$legacyPayment) {
            return;
        }
        $creditCard = $paymentRepository->findOneBy(['method_class' => CreditCard::class]);
        if (!$creditCard) {
            throw new \RuntimeException('[UnivaPay] credit card payment not found');
        }
        if (!$fetchSubscriptionStatus) {
            $fetchSubscriptionStatus = self::apiStatusFetcher($entityManager);
        }
        $legacyPaymentId = $legacyPayment->getId();
        $creditCardPaymentId = $creditCard->getId();

        $firstOrderIds = [];
        $chargeIdCounts = [];
        $legacyOrders = $connection->fetchAll(
            'SELECT id, univa_pay_subscription_id, univa_pay_charge_id FROM dtb_order WHERE payment_id = ? ORDER BY id',
            [$legacyPaymentId]
        );
        foreach ($legacyOrders as $order) {
            $subscriptionId = $order['univa_pay_subscription_id'];
            if ($subscriptionId === null) {
                continue;
            }
            if (!isset($firstOrderIds[$subscriptionId])) {
                $firstOrderIds[$subscriptionId] = $order['id'];
            }
            if ($order['univa_pay_charge_id'] !== null) {
                $chargeId = $order['univa_pay_charge_id'];
                $chargeIdCounts[$chargeId] = (isset($chargeIdCounts[$chargeId]) ? $chargeIdCounts[$chargeId] : 0) + 1;
            }
        }

        // univa_pay_subscription_id is left as is so the original 1.5.0 data survives.
        $connection->executeUpdate(
            'UPDATE dtb_order SET univa_pay_parent_subscription_id = univa_pay_subscription_id WHERE payment_id = ? AND univa_pay_subscription_id IS NOT NULL',
            [$legacyPaymentId]
        );
        $connection->executeUpdate(
            'UPDATE dtb_order SET payment_id = ?, payment_method = ? WHERE payment_id = ?',
            [$creditCardPaymentId, Constants::UNIVAPAY_PAYMENT_METHOD, $legacyPaymentId]
        );

        $statusCounts = [];
        foreach ($firstOrderIds as $subscriptionId => $firstOrderId) {
            $statusId = self::resolveParentStatus($subscriptionId, $fetchSubscriptionStatus);
            $parentId = self::copyOrderAsSubscriptionParent($connection, $firstOrderId, $statusId);
            $orderNoProcessor->process($entityManager->find(Order::class, $parentId), new PurchaseContext());
            $entityManager->flush();

            $statusCounts[$statusId] = (isset($statusCounts[$statusId]) ? $statusCounts[$statusId] : 0) + 1;
        }

        self::movePaymentOptions($connection, $legacyPaymentId, $creditCardPaymentId);
        $connection->executeUpdate('DELETE FROM dtb_payment WHERE id = ?', [$legacyPaymentId]);

        UnivaPayLogger::get()->info('[UnivaPay] legacy subscriptions migrated', [
            'parents_by_status' => $statusCounts,
            'duplicate_charge_ids' => array_filter($chargeIdCounts, function ($count) {
                return $count > 1;
            }),
        ]);
    }

    private static function movePaymentOptions(Connection $connection, $legacyPaymentId, $creditCardPaymentId)
    {
        $deliveryIds = $connection->fetchAll('SELECT delivery_id FROM dtb_payment_option WHERE payment_id = ?', [$legacyPaymentId]);
        foreach ($deliveryIds as $row) {
            $exists = $connection->fetchColumn(
                'SELECT 1 FROM dtb_payment_option WHERE delivery_id = ? AND payment_id = ?',
                [$row['delivery_id'], $creditCardPaymentId]
            );
            if ($exists === false) {
                $connection->insert('dtb_payment_option', [
                    'delivery_id' => $row['delivery_id'],
                    'payment_id' => $creditCardPaymentId,
                    'discriminator_type' => 'paymentoption',
                ]);
            }
        }
        $connection->executeUpdate('DELETE FROM dtb_payment_option WHERE payment_id = ?', [$legacyPaymentId]);
    }

    /**
     * Same result as OrderCopier, at row level: product, fee and tax items and the shippings are copied, discount and
     * point items are not, and shipping dates and tracking numbers are cleared. Returns the new order id.
     */
    private static function copyOrderAsSubscriptionParent(Connection $connection, $orderId, $statusId)
    {
        $now = (new \DateTime())->format('Y-m-d H:i:s');

        $order = $connection->fetchAssoc('SELECT * FROM dtb_order WHERE id = ?', [$orderId]);
        unset($order['id']);
        $connection->insert('dtb_order', array_merge($order, [
            'pre_order_id' => null,
            'order_no' => null,
            'univa_pay_charge_id' => null,
            'univa_pay_subscription_id' => null,
            'order_status_id' => $statusId,
            'payment_date' => null,
            'add_point' => 0,
            'use_point' => 0,
            'create_date' => $now,
            'update_date' => $now,
        ]));
        // PostgreSQL needs the sequence name; MySQL ignores it.
        $parentId = $connection->lastInsertId('dtb_order_id_seq');

        $shippingIds = [];
        foreach ($connection->fetchAll('SELECT * FROM dtb_shipping WHERE order_id = ? ORDER BY id', [$orderId]) as $shipping) {
            $oldShippingId = $shipping['id'];
            unset($shipping['id']);
            $connection->insert('dtb_shipping', array_merge($shipping, [
                'order_id' => $parentId,
                'delivery_date' => null,
                'delivery_time' => null,
                'time_id' => null,
                'shipping_date' => null,
                'tracking_number' => null,
                'mail_send_date' => null,
                'create_date' => $now,
                'update_date' => $now,
            ]));
            $shippingIds[$oldShippingId] = $connection->lastInsertId('dtb_shipping_id_seq');
        }

        $items = $connection->fetchAll(
            'SELECT * FROM dtb_order_item WHERE order_id = ? AND order_item_type_id NOT IN (?, ?) ORDER BY id',
            [$orderId, OrderItemType::DISCOUNT, OrderItemType::POINT]
        );
        foreach ($items as $item) {
            unset($item['id']);
            $item['order_id'] = $parentId;
            $item['shipping_id'] = isset($shippingIds[$item['shipping_id']]) ? $shippingIds[$item['shipping_id']] : null;
            $connection->insert('dtb_order_item', $item);
        }

        return $parentId;
    }

    private static function resolveParentStatus($subscriptionId, callable $fetchSubscriptionStatus)
    {
        try {
            $status = $fetchSubscriptionStatus($subscriptionId);
        } catch (\Throwable $e) {
            UnivaPayLogger::get()->warning('[UnivaPay] subscription status lookup failed, migrated as active', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            return UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE;
        }

        switch ($status) {
            case SubscriptionStatus::SUSPENDED:
                return UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_SUSPEND;
            case SubscriptionStatus::CANCELED:
            case SubscriptionStatus::COMPLETED:
                return UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_CANCEL;
            default:
                return UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE;
        }
    }
}
