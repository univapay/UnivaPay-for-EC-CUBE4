<?php
namespace Plugin\UnivaPay;

use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Master\OrderStatusColor;
use Eccube\Entity\Master\CustomerOrderStatus;
use Eccube\Entity\MailTemplate;
use Eccube\Entity\Payment;
use Eccube\Plugin\AbstractPluginManager;
use Plugin\UnivaPay\Entity\Config;
use Plugin\UnivaPay\Entity\SubscriptionPeriod;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;
use Plugin\UnivaPay\Service\Method\CreditCard;
use Plugin\UnivaPay\Util\Constants;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PluginManager extends AbstractPluginManager
{
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

    private function addMasterOrderStatus(ContainerInterface $container)
    {
        $data = [
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE => Constants::MASTER_DATA_UNIVAPAY_SUBSCRIPTION_NAME,
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_CANCEL => Constants::MASTER_DATA_UNIVAPAY_CANCEL_NAME,
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_SUSPEND => Constants::MASTER_DATA_UNIVAPAY_SUSPEND_NAME
        ];

        $this->createMasterData($container, $data, OrderStatus::class);
    }

    private function addMasterOrderStatusColor(ContainerInterface $container)
    {
        $data = [
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE => '#A3A3A3',
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_CANCEL => '#A3A3A3',
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_SUSPEND => '#A3A3A3'
        ];

        $this->createMasterData($container, $data, OrderStatusColor::class);
    }

    private function addMasterCustomerOrderStatus(ContainerInterface $container)
    {
        $data = [
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE => Constants::MASTER_DATA_UNIVAPAY_SUBSCRIPTION_NAME,
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_CANCEL => Constants::MASTER_DATA_UNIVAPAY_CANCEL_NAME,
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_SUSPEND => Constants::MASTER_DATA_UNIVAPAY_SUSPEND_NAME
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
                $entity= new $class();
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
            Constants::MAIL_TEMPLATE_UNIVAPAY_SUBSCRIPTION_ACTIVE =>
            [
                "subject" => 'サブスクリプションのご登録ありがとうございます',
                "fileName" => 'UnivaPay/Resource/template/mail/subscription_mail.twig'
            ],
            Constants::MAIL_TEMPLATE_UNIVAPAY_SUBSCRIPTION_CANCEL =>
            [
                "subject" => 'サブスクリプション停止',
                "fileName" => 'UnivaPay/Resource/template/mail/subscription_mail_cancel.twig'
            ]
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
                $PaymentStatus = new $class;
            }
            $PaymentStatus->setId($id);
            $PaymentStatus->setName($name);
            $PaymentStatus->setSortNo($id);
            $entityManager->persist($PaymentStatus);
            $entityManager->flush($PaymentStatus);
        }
    }

    private function createSubscriptionPeriod(ContainerInterface $container) {
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
