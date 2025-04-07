<?php

namespace Plugin\UnivaPay\Tests;

use Eccube\Entity\MailTemplate;
use Eccube\Entity\Master\CustomerOrderStatus;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Master\OrderStatusColor;
use Eccube\Entity\Payment;
use Eccube\Tests\EccubeTestCase;
use Plugin\UnivaPay\PluginManager;
use Plugin\UnivaPay\Entity\Config;
use Plugin\UnivaPay\Entity\SubscriptionPeriod;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;
use Plugin\UnivaPay\Service\Method\CreditCard;
use Plugin\UnivaPay\Util\Constants;

/**
 * Test Enable/Disable Plugin
 */
class PluginManagerTest extends EccubeTestCase
{
    public function testEnable()
    {
        $pluginManager = new PluginManager();
        $pluginManager->enable([], $this->container);
        $this->verifyPluginMasterData();
    }

    public function testDisable()
    {
        $pluginManager = new PluginManager();
        $pluginManager->enable([], $this->container);
        $pluginManager->disable([], $this->container);
        $pluginManager->enable([], $this->container);
        $this->verifyPluginMasterData();
    }

    private function verifyPluginMasterData()
    {
        $paymentRepository = $this->container->get('doctrine')->getRepository(Payment::class);
        $payment = $paymentRepository->findOneBy(['method_class' => CreditCard::class]);
        $this->assertNotNull($payment);
        $this->assertEquals(Constants::UNIVAPAY_PAYMENT_METHOD, $payment->getMethod());

        $configRepository = $this->container->get('doctrine')->getRepository(Config::class);
        $config = $configRepository->findAll()[0];
        $this->assertNotNull($config);
        $this->assertEmpty($config->getAppId());
        $this->assertEmpty($config->getAppSecret());
        $this->assertEquals(Constants::UNIVAPAY_WIDGET_URL, $config->getWidgetUrl());
        $this->assertEquals(Constants::UNIVAPAY_API_URL, $config->getApiUrl());
        $this->assertFalse($config->getCapture());

        $subscriptionPeriodRepository = $this->container->get('doctrine')->getRepository(SubscriptionPeriod::class);
        $subscriptionPeriods = $subscriptionPeriodRepository->findAll();
        $this->assertCount(9, $subscriptionPeriods);

        $orderStatusRepository = $this->container->get('doctrine')->getRepository(OrderStatus::class);
        $orderStatus = $orderStatusRepository->findBy([
            'id' => [
                UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE,
                UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_CANCEL,
                UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_SUSPEND
            ]
        ]);
        $this->assertCount(3, $orderStatus);

        $orderStatusColorRepository = $this->container->get('doctrine')->getRepository(OrderStatusColor::class);
        $orderStatusColor = $orderStatusColorRepository->findBy([
            'id' => [
                UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE,
                UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_CANCEL,
                UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_SUSPEND
            ]
        ]);
        $this->assertCount(3, $orderStatusColor);

        $customerOrderStatusColorRepository = $this->container->get('doctrine')->getRepository(CustomerOrderStatus::class);
        $customerOrderStatusColor = $customerOrderStatusColorRepository->findBy([
            'id' => [
                UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE,
                UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_CANCEL,
                UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_SUSPEND
            ]
        ]);
        $this->assertCount(3, $customerOrderStatusColor);

        $mailTemplateRepository = $this->container->get('doctrine')->getRepository(MailTemplate::class);
        $mailTemplate = $mailTemplateRepository->findBy([
            'name' => [
                Constants::MAIL_TEMPLATE_UNIVAPAY_SUBSCRIPTION_ACTIVE,
                Constants::MAIL_TEMPLATE_UNIVAPAY_SUBSCRIPTION_CANCEL
            ]
        ]);
        $this->assertCount(2, $mailTemplate);
    }
}
