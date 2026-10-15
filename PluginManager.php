<?php

namespace Plugin\UnivaPay;

require_once __DIR__.'/Resource/vendor/autoload.php';

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Eccube\Common\EccubeConfig;
use Eccube\Entity\MailTemplate;
use Eccube\Entity\Master\CustomerOrderStatus;
use Eccube\Entity\Master\OrderItemType;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Master\OrderStatusColor;
use Eccube\Entity\Order;
use Eccube\Entity\Payment;
use Eccube\Plugin\AbstractPluginManager;
use Eccube\Service\PurchaseFlow\Processor\OrderNoProcessor;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Plugin\UnivaPay\Entity\Config;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;
use Plugin\UnivaPay\Entity\SubscriptionPeriod;
use Plugin\UnivaPay\Service\Method\CreditCard;
use Plugin\UnivaPay\Util\Constants;
use Plugin\UnivaPay\Util\SDK;
use Plugin\UnivaPay\Util\UnivaPayLogger;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UnivaPay\Models\SubscriptionStatus;

class PluginManager extends AbstractPluginManager
{
    /**
     * Only installs up to 1.5.0 have a dtb_payment row with this (now deleted) class.
     */
    const LEGACY_SUBSCRIPTION_METHOD_CLASS = 'Plugin\UnivaPay\Service\Method\Subscription';

    public function enable(array $meta, ContainerInterface $container)
    {
        $this->seedMasterData($container);
    }

    /**
     * An admin-upload update runs in a request that still has the 1.5.0 plugin classes loaded: EC-CUBE's schema
     * update sees no change and plugin entities, traits and constants are the old ones. So the schema changes go
     * through a Doctrine migration and the order data through DBAL; only core entities are used via the ORM.
     */
    public function update(array $meta, ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine')->getManager();
        $this->migration($entityManager->getConnection(), $meta['code']);
        $this->migrateFrom150($container);
    }

    private function seedMasterData(ContainerInterface $container)
    {
        $this->createTokenPayment($container);
        $this->createConfig($container);
        $this->createSubscriptionPeriod($container);
        $this->addMasterOrderStatus($container);
        $this->addMasterOrderStatusColor($container);
        $this->addMasterCustomerOrderStatus($container);
        $this->addSubscriptionMailTemplate($container);
    }

    public function migrateFrom150(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine')->getManager();
        $connection = $entityManager->getConnection();
        if ($connection->fetchColumn('SELECT id FROM dtb_payment WHERE method_class = ?', [self::LEGACY_SUBSCRIPTION_METHOD_CLASS]) === false) {
            return;
        }

        $this->seedMasterData($container);
        $orderNoProcessor = new OrderNoProcessor($container->get(EccubeConfig::class), $entityManager->getRepository(Order::class));

        $config = $connection->fetchAssoc('SELECT app_id, app_secret FROM plg_univa_pay_config WHERE id = 1');
        if (!$config || !$config['app_id'] || !$config['app_secret']) {
            UnivaPayLogger::get()->warning('[UnivaPay] API credentials not set, legacy subscriptions migrated as active');
            $this->migrateLegacySubscriptions($entityManager, $orderNoProcessor, function () {
                return null;
            });

            return;
        }

        $sdk = null;
        $this->migrateLegacySubscriptions($entityManager, $orderNoProcessor, function ($subscriptionId) use ($entityManager, &$sdk) {
            if (!$sdk) {
                $sdk = new SDK($entityManager->find(Config::class, 1));
            }

            return $sdk->getSubscription($subscriptionId)->getStatus();
        });
    }

    /**
     * 1.5.0 used the first order as the parent, so this creates a new parent order without a charge id per subscription
     * and copies univa_pay_parent_subscription_id to every existing order. Existing charge ids and statuses are not changed.
     */
    public function migrateLegacySubscriptions(EntityManagerInterface $entityManager, OrderNoProcessor $orderNoProcessor, callable $fetchSubscriptionStatus)
    {
        $connection = $entityManager->getConnection();
        $legacyPaymentId = $connection->fetchColumn('SELECT id FROM dtb_payment WHERE method_class = ?', [self::LEGACY_SUBSCRIPTION_METHOD_CLASS]);
        if ($legacyPaymentId === false) {
            return;
        }
        $creditCardPaymentId = $connection->fetchColumn('SELECT id FROM dtb_payment WHERE method_class = ?', [CreditCard::class]);

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
            $statusId = $this->resolveLegacyParentStatus($subscriptionId, $fetchSubscriptionStatus);
            $parentId = $this->copyOrderAsSubscriptionParent($connection, $firstOrderId, $statusId);
            $orderNoProcessor->process($entityManager->find(Order::class, $parentId), new PurchaseContext());
            $entityManager->flush();

            $statusCounts[$statusId] = (isset($statusCounts[$statusId]) ? $statusCounts[$statusId] : 0) + 1;
        }

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
        $connection->executeUpdate('DELETE FROM dtb_payment WHERE id = ?', [$legacyPaymentId]);

        UnivaPayLogger::get()->info('[UnivaPay] legacy subscriptions migrated', [
            'parents_by_status' => $statusCounts,
            'duplicate_charge_ids' => array_filter($chargeIdCounts, function ($count) {
                return $count > 1;
            }),
        ]);
    }

    /**
     * Same result as OrderCopier, at row level: product, fee and tax items and the shippings are copied, discount and
     * point items are not, and shipping dates and tracking numbers are cleared. Returns the new order id.
     */
    private function copyOrderAsSubscriptionParent(Connection $connection, $orderId, $statusId)
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

    private function resolveLegacyParentStatus($subscriptionId, callable $fetchSubscriptionStatus)
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

    private function addMasterOrderStatus(ContainerInterface $container)
    {
        $data = [
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE => Constants::MASTER_DATA_UNIVAPAY_SUBSCRIPTION_NAME,
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_CANCEL => Constants::MASTER_DATA_UNIVAPAY_CANCEL_NAME,
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_SUSPEND => Constants::MASTER_DATA_UNIVAPAY_SUSPEND_NAME,
        ];

        $this->createMasterData($container, $data, OrderStatus::class);
    }

    private function addMasterOrderStatusColor(ContainerInterface $container)
    {
        $data = [
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE => '#A3A3A3',
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_CANCEL => '#A3A3A3',
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_SUSPEND => '#A3A3A3',
        ];

        $this->createMasterData($container, $data, OrderStatusColor::class);
    }

    private function addMasterCustomerOrderStatus(ContainerInterface $container)
    {
        $data = [
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE => Constants::MASTER_DATA_UNIVAPAY_SUBSCRIPTION_NAME,
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_CANCEL => Constants::MASTER_DATA_UNIVAPAY_CANCEL_NAME,
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_SUSPEND => Constants::MASTER_DATA_UNIVAPAY_SUSPEND_NAME,
        ];

        $this->createMasterData($container, $data, CustomerOrderStatus::class);
    }

    private function createMailTemplate(ContainerInterface $container, array $data, $class)
    {
        $entityManager = $container->get('doctrine')->getManager();
        $repository = $entityManager->getRepository($class);

        foreach ($data as $name => $item) {
            $entity = $repository->findOneBy(['name' => $name]);
            if (!$entity) {
                $entity = new $class();
            }
            $entity->setName($name);
            $entity->setMailSubject($item['subject']);
            $entity->setFileName($item['fileName']);
            $entityManager->persist($entity);
            $entityManager->flush($entity);
        }
    }

    private function addSubscriptionMailTemplate(ContainerInterface $container)
    {
        $data = [
            Constants::MAIL_TEMPLATE_UNIVAPAY_SUBSCRIPTION_ACTIVE => [
                'subject' => 'サブスクリプションのご登録ありがとうございます',
                'fileName' => 'UnivaPay/Resource/template/mail/subscription_mail.twig',
            ],
            Constants::MAIL_TEMPLATE_UNIVAPAY_SUBSCRIPTION_CANCEL => [
                'subject' => 'サブスクリプション停止',
                'fileName' => 'UnivaPay/Resource/template/mail/subscription_mail_cancel.twig',
            ],
        ];
        $this->createMailTemplate($container, $data, MailTemplate::class);
    }

    private function createTokenPayment(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine')->getManager();
        $paymentRepository = $entityManager->getRepository(Payment::class);

        $Payment = $paymentRepository->findOneBy([], ['sort_no' => 'DESC']);
        $sortNo = $Payment ? $Payment->getSortNo() + 1 : 1;

        $Payment = $paymentRepository->findOneBy(['method_class' => CreditCard::class]);
        if ($Payment) {
            return;
        }

        $Payment = new Payment();
        $Payment->setCharge(0);
        $Payment->setSortNo($sortNo);
        $Payment->setVisible(true);
        $Payment->setMethod(Constants::UNIVAPAY_PAYMENT_METHOD);
        $Payment->setMethodClass(CreditCard::class);

        $entityManager->persist($Payment);
        $entityManager->flush($Payment);
    }

    private function createConfig(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $Config = $entityManager->find(Config::class, 1);
        if ($Config) {
            return;
        }

        $Config = new Config();
        $Config->setAppId('');
        $Config->setAppSecret('');
        $Config->setWidgetUrl(Constants::UNIVAPAY_WIDGET_URL);
        $Config->setApiUrl(Constants::UNIVAPAY_API_URL);
        $Config->setCapture(false);

        $entityManager->persist($Config);
        $entityManager->flush($Config);
    }

    private function createMasterData(ContainerInterface $container, array $statuses, $class)
    {
        $entityManager = $container->get('doctrine')->getManager();
        foreach ($statuses as $id => $name) {
            $PaymentStatus = $entityManager->find($class, $id);
            if (!$PaymentStatus) {
                $PaymentStatus = new $class();
            }
            $PaymentStatus->setId($id);
            $PaymentStatus->setName($name);
            $PaymentStatus->setSortNo($id);
            $entityManager->persist($PaymentStatus);
            $entityManager->flush($PaymentStatus);
        }
    }

    private function createSubscriptionPeriod(ContainerInterface $container)
    {
        // Literal ids: update() runs this with the 1.5.0 SubscriptionPeriod class loaded, which lacks NON_SUBSCRIPTION.
        $statuses = [
            0 => '非定期',
            1 => '毎日',
            2 => '毎週',
            3 => '隔週',
            4 => '毎月',
            5 => '隔月',
            6 => '3ヶ月',
            7 => '6ヶ月',
            8 => '毎年',
        ];
        $this->createMasterData($container, $statuses, SubscriptionPeriod::class);
    }
}
