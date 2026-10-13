<?php

namespace Plugin\UnivaPay;

require_once __DIR__.'/Resource/vendor/autoload.php';

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Common\EccubeConfig;
use Eccube\Entity\MailTemplate;
use Eccube\Entity\Master\CustomerOrderStatus;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Master\OrderStatusColor;
use Eccube\Entity\Order;
use Eccube\Entity\Payment;
use Eccube\Entity\PaymentOption;
use Eccube\Plugin\AbstractPluginManager;
use Eccube\Service\PurchaseFlow\Processor\OrderNoProcessor;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Plugin\UnivaPay\Entity\Config;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;
use Plugin\UnivaPay\Entity\SubscriptionPeriod;
use Plugin\UnivaPay\Service\Method\CreditCard;
use Plugin\UnivaPay\Util\Constants;
use Plugin\UnivaPay\Util\OrderCopier;
use Plugin\UnivaPay\Util\SDK;
use Plugin\UnivaPay\Util\UnivaPayLogger;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UnivaPay\Models\SubscriptionStatus;

class PluginManager extends AbstractPluginManager
{
    /**
     * Only installs up to 1.5.0 have a dtb_payment row with this (now deleted) class.
     *
     * @deprecated 1.5.0 migration only. Remove in the next release.
     */
    const LEGACY_SUBSCRIPTION_METHOD_CLASS = 'Plugin\UnivaPay\Service\Method\Subscription';

    public function enable(array $meta, ContainerInterface $container)
    {
        $this->createTokenPayment($container);
        $this->createConfig($container);
        $this->createSubscriptionPeriod($container);
        $this->addMasterOrderStatus($container);
        $this->addMasterOrderStatusColor($container);
        $this->addMasterCustomerOrderStatus($container);
        $this->addSubscriptionMailTemplate($container);
    }

    public function update(array $meta, ContainerInterface $container)
    {
        // when migrating from 1.5.0. Deprecated: remove this body with migrateLegacySubscriptions() in the next release.
        $entityManager = $container->get('doctrine')->getManager();
        if (!$entityManager->getRepository(Payment::class)->findOneBy(['method_class' => self::LEGACY_SUBSCRIPTION_METHOD_CLASS])) {
            return;
        }

        $this->enable($meta, $container);

        $orderNoProcessor = new OrderNoProcessor($container->get(EccubeConfig::class), $entityManager->getRepository(Order::class));

        $config = $entityManager->find(Config::class, 1);
        if (!$config || !$config->getAppId() || !$config->getAppSecret()) {
            UnivaPayLogger::get()->warning('[UnivaPay] API credentials not set, legacy subscriptions migrated as active');
            $this->migrateLegacySubscriptions($entityManager, $orderNoProcessor, function () {
                return null;
            });

            return;
        }

        $sdk = null;
        $this->migrateLegacySubscriptions($entityManager, $orderNoProcessor, function ($subscriptionId) use ($config, &$sdk) {
            if (!$sdk) {
                $sdk = new SDK($config);
            }

            return $sdk->getSubscription($subscriptionId)->getStatus();
        });
    }

    /**
     * 1.5.0 used the first order as the parent, so this creates a new parent order without a charge id per subscription
     * and copies univa_pay_parent_subscription_id to every existing order. Existing charge ids and statuses are not changed.
     *
     * @deprecated 1.5.0 migration only. Remove in the next release.
     */
    public function migrateLegacySubscriptions(EntityManagerInterface $entityManager, OrderNoProcessor $orderNoProcessor, callable $fetchSubscriptionStatus)
    {
        $paymentRepository = $entityManager->getRepository(Payment::class);
        $LegacyPayment = $paymentRepository->findOneBy(['method_class' => self::LEGACY_SUBSCRIPTION_METHOD_CLASS]);
        if (!$LegacyPayment) {
            return;
        }
        $CreditCardPayment = $paymentRepository->findOneBy(['method_class' => CreditCard::class]);

        // ponytail: loads every legacy order into one unit of work; batch with flush/clear if shops have too many for memory
        $legacyOrders = $entityManager->getRepository(Order::class)->findBy(['Payment' => $LegacyPayment], ['id' => 'ASC']);
        $firstOrders = [];
        $chargeIdCounts = [];
        foreach ($legacyOrders as $order) {
            $order->setPayment($CreditCardPayment);
            $order->setPaymentMethod(Constants::UNIVAPAY_PAYMENT_METHOD);

            $subscriptionId = $order->getUnivapaySubscriptionId();
            if ($subscriptionId === null) {
                continue;
            }
            // univa_pay_subscription_id is left as is so 1.5.0 data survives until the column is dropped.
            $order->setUnivapayParentSubscriptionId($subscriptionId);
            if (!isset($firstOrders[$subscriptionId])) {
                $firstOrders[$subscriptionId] = $order;
            }
            if ($order->getUnivapayChargeId() !== null) {
                $chargeId = $order->getUnivapayChargeId();
                $chargeIdCounts[$chargeId] = (isset($chargeIdCounts[$chargeId]) ? $chargeIdCounts[$chargeId] : 0) + 1;
            }
        }

        $statusCounts = [];
        foreach ($firstOrders as $subscriptionId => $firstOrder) {
            $statusId = $this->resolveLegacyParentStatus($subscriptionId, $fetchSubscriptionStatus);

            $subscriptionOrder = OrderCopier::copy($firstOrder);
            $subscriptionOrder->setOrderDate($firstOrder->getOrderDate());
            $subscriptionOrder->setOrderStatus($entityManager->find(OrderStatus::class, $statusId));
            $entityManager->persist($subscriptionOrder);
            $entityManager->flush();
            $orderNoProcessor->process($subscriptionOrder, new PurchaseContext());
            $entityManager->flush();

            $statusCounts[$statusId] = (isset($statusCounts[$statusId]) ? $statusCounts[$statusId] : 0) + 1;
        }

        $paymentOptionRepository = $entityManager->getRepository(PaymentOption::class);
        foreach ($paymentOptionRepository->findBy(['Payment' => $LegacyPayment]) as $LegacyOption) {
            $Delivery = $LegacyOption->getDelivery();
            if (!$paymentOptionRepository->findOneBy(['Delivery' => $Delivery, 'Payment' => $CreditCardPayment])) {
                $Option = new PaymentOption();
                $Option->setDeliveryId($Delivery->getId());
                $Option->setDelivery($Delivery);
                $Option->setPaymentId($CreditCardPayment->getId());
                $Option->setPayment($CreditCardPayment);
                $entityManager->persist($Option);
            }
            $entityManager->remove($LegacyOption);
        }
        $entityManager->remove($LegacyPayment);
        $entityManager->flush();

        UnivaPayLogger::get()->info('[UnivaPay] legacy subscriptions migrated', [
            'parents_by_status' => $statusCounts,
            'duplicate_charge_ids' => array_filter($chargeIdCounts, function ($count) {
                return $count > 1;
            }),
        ]);
    }

    /**
     * @deprecated 1.5.0 migration only. Remove in the next release.
     */
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
        $statuses = [
            SubscriptionPeriod::NON_SUBSCRIPTION => '非定期',
            SubscriptionPeriod::DAILY => '毎日',
            SubscriptionPeriod::WEEKLY => '毎週',
            SubscriptionPeriod::BIWEEKLY => '隔週',
            SubscriptionPeriod::MONTHLY => '毎月',
            SubscriptionPeriod::BIMONTHLY => '隔月',
            SubscriptionPeriod::QUARTERLY => '3ヶ月',
            SubscriptionPeriod::SEMIANNUALLY => '6ヶ月',
            SubscriptionPeriod::ANNUALLY => '毎年',
        ];
        $this->createMasterData($container, $statuses, SubscriptionPeriod::class);
    }
}
