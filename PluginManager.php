<?php

namespace Plugin\UnivaPay;

require_once __DIR__.'/Resource/vendor/autoload.php';

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\MailTemplate;
use Eccube\Entity\Master\CustomerOrderStatus;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Master\OrderStatusColor;
use Eccube\Entity\Payment;
use Eccube\Entity\PaymentOption;
use Eccube\Plugin\AbstractPluginManager;
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
    // Only installs up to 1.5.0 have a dtb_payment row with this (now deleted) class.
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
        // when migrating from 1.5.0
        $entityManager = $container->get('doctrine')->getManager();
        if (!$entityManager->getRepository(Payment::class)->findOneBy(['method_class' => self::LEGACY_SUBSCRIPTION_METHOD_CLASS])) {
            return;
        }

        $this->enable($meta, $container);

        $config = $entityManager->find(Config::class, 1);
        if (!$config || !$config->getAppId() || !$config->getAppSecret()) {
            UnivaPayLogger::get()->warning('[UnivaPay] API credentials not set, legacy subscriptions migrated as active');
            $this->migrateLegacySubscriptions($entityManager, function () {
                return null;
            });

            return;
        }

        $sdk = null;
        $this->migrateLegacySubscriptions($entityManager, function ($subscriptionId) use ($config, &$sdk) {
            if (!$sdk) {
                $sdk = new SDK($config);
            }

            return $sdk->getSubscription($subscriptionId)->getStatus();
        });
    }

    public function migrateLegacySubscriptions(EntityManagerInterface $entityManager, callable $fetchSubscriptionStatus)
    {
        $paymentRepository = $entityManager->getRepository(Payment::class);
        $LegacyPayment = $paymentRepository->findOneBy(['method_class' => self::LEGACY_SUBSCRIPTION_METHOD_CLASS]);
        if (!$LegacyPayment) {
            return;
        }
        $CreditCardPayment = $paymentRepository->findOneBy(['method_class' => CreditCard::class]);

        $parents = $entityManager->createQuery(
            'SELECT MIN(o.id) AS id, o.univa_pay_subscription_id AS subscription_id FROM Eccube\Entity\Order o
             WHERE o.Payment = :legacy AND o.univa_pay_subscription_id IS NOT NULL
             GROUP BY o.univa_pay_subscription_id'
        )->setParameter('legacy', $LegacyPayment)->getArrayResult();

        $statusCounts = [];
        foreach ($parents as $parent) {
            $statusId = $this->resolveLegacyParentStatus($parent['subscription_id'], $fetchSubscriptionStatus);
            $entityManager->createQuery(
                'UPDATE Eccube\Entity\Order o SET o.OrderStatus = :status, o.univa_pay_charge_id = NULL WHERE o.id = :id'
            )->setParameters(['status' => $statusId, 'id' => $parent['id']])->execute();
            $statusCounts[$statusId] = (isset($statusCounts[$statusId]) ? $statusCounts[$statusId] : 0) + 1;
        }

        $entityManager->createQuery(
            'UPDATE Eccube\Entity\Order o SET o.Payment = :card, o.payment_method = :method WHERE o.Payment = :legacy'
        )->setParameters([
            'card' => $CreditCardPayment,
            'method' => Constants::UNIVAPAY_PAYMENT_METHOD,
            'legacy' => $LegacyPayment,
        ])->execute();

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

        $duplicateCharges = $entityManager->createQuery(
            'SELECT o.univa_pay_charge_id AS charge_id, COUNT(o.id) AS orders FROM Eccube\Entity\Order o
             WHERE o.univa_pay_subscription_id IS NOT NULL AND o.univa_pay_charge_id IS NOT NULL
             GROUP BY o.univa_pay_charge_id HAVING COUNT(o.id) > 1'
        )->getArrayResult();

        UnivaPayLogger::get()->info('[UnivaPay] legacy subscriptions migrated', [
            'parents_by_status' => $statusCounts,
            'duplicate_charge_ids' => $duplicateCharges,
        ]);
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
