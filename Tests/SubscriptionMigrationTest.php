<?php

namespace Plugin\UnivaPay\Tests;

use Eccube\Entity\Delivery;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Entity\Payment;
use Eccube\Entity\PaymentOption;
use Eccube\Tests\EccubeTestCase;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;
use Plugin\UnivaPay\PluginManager;
use Plugin\UnivaPay\Service\Method\CreditCard;
use Plugin\UnivaPay\Util\Constants;

class SubscriptionMigrationTest extends EccubeTestCase
{
    /** @var PluginManager */
    private $pluginManager;

    /** @var Payment */
    private $legacyPayment;

    /** @var Delivery */
    private $delivery;

    public function setUp()
    {
        parent::setUp();

        $this->pluginManager = new PluginManager();
        $this->pluginManager->enable([], $this->client->getContainer());

        $this->legacyPayment = new Payment();
        $this->legacyPayment->setMethod('UnivaPay(Subscription)')
            ->setMethodClass(PluginManager::LEGACY_SUBSCRIPTION_METHOD_CLASS)
            ->setCharge(0)
            ->setSortNo(999)
            ->setVisible(true);
        $this->entityManager->persist($this->legacyPayment);
        $this->entityManager->flush();

        $this->delivery = $this->entityManager->getRepository(Delivery::class)->find(1);
        $option = new PaymentOption();
        $option->setDeliveryId($this->delivery->getId())
            ->setDelivery($this->delivery)
            ->setPaymentId($this->legacyPayment->getId())
            ->setPayment($this->legacyPayment);
        $this->entityManager->persist($option);
        $this->entityManager->flush();
    }

    public function testMigratesParentsAndKeepsChildrenLinked()
    {
        $parentA = $this->createLegacyOrder('sub-a', 'charge-a1', OrderStatus::PAID);
        $childA2 = $this->createLegacyOrder('sub-a', 'charge-a2', OrderStatus::PAID);
        $childA3 = $this->createLegacyOrder('sub-a', 'charge-a3', OrderStatus::CANCEL);
        $parentB = $this->createLegacyOrder('sub-b', 'charge-b1', OrderStatus::PAID);
        $parentC = $this->createLegacyOrder('sub-c', null, OrderStatus::PAID);
        $oneTime = $this->createOneTimeOrder('charge-one-time');

        $this->migrate();

        $this->assertParent($parentA, 'sub-a', UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE);
        $this->assertParent($parentB, 'sub-b', UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_CANCEL);
        $this->assertParent($parentC, 'sub-c', UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE);

        $this->assertChild($childA2, 'sub-a', 'charge-a2', OrderStatus::PAID);
        $this->assertChild($childA3, 'sub-a', 'charge-a3', OrderStatus::CANCEL);

        $oneTime = $this->reload($oneTime);
        $this->assertSame('charge-one-time', $oneTime->getUnivapayChargeId());
        $this->assertNull($oneTime->getUnivapaySubscriptionId());
        $this->assertSame(OrderStatus::PAID, $oneTime->getOrderStatus()->getId());

        $paymentRepository = $this->entityManager->getRepository(Payment::class);
        $this->assertNull($paymentRepository->findOneBy(['method_class' => PluginManager::LEGACY_SUBSCRIPTION_METHOD_CLASS]));
        $creditCard = $paymentRepository->findOneBy(['method_class' => CreditCard::class]);
        $this->assertNotNull($this->entityManager->getRepository(PaymentOption::class)->findOneBy([
            'Delivery' => $this->delivery,
            'Payment' => $creditCard,
        ]));
    }

    public function testUpdateWithoutCredentialsMigratesAsActive()
    {
        $parent = $this->createLegacyOrder('sub-b', 'charge-b1', OrderStatus::PAID);
        $child = $this->createLegacyOrder('sub-b', 'charge-b2', OrderStatus::PAID);

        $this->pluginManager->update([], $this->client->getContainer());

        $this->assertParent($parent, 'sub-b', UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE);
        $this->assertChild($child, 'sub-b', 'charge-b2', OrderStatus::PAID);
    }

    public function testSecondRunChangesNothing()
    {
        $parent = $this->createLegacyOrder('sub-a', 'charge-a1', OrderStatus::PAID);
        $child = $this->createLegacyOrder('sub-a', 'charge-a2', OrderStatus::PAID);

        $this->migrate();
        $this->pluginManager->migrateLegacySubscriptions($this->entityManager, function () {
            $this->fail('status must not be fetched once the legacy payment is gone');
        });

        $this->assertParent($parent, 'sub-a', UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE);
        $this->assertChild($child, 'sub-a', 'charge-a2', OrderStatus::PAID);
    }

    public function testUpdateSkipsInstallsNotFrom150()
    {
        $this->entityManager->remove($this->legacyPayment);
        foreach ($this->entityManager->getRepository(PaymentOption::class)->findBy(['Payment' => $this->legacyPayment]) as $option) {
            $this->entityManager->remove($option);
        }
        $this->entityManager->flush();
        $order = $this->createOneTimeOrder('charge-x');
        $activeStatus = $this->entityManager->find(OrderStatus::class, UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE);
        $activeStatus->setName('renamed by merchant');
        $this->entityManager->flush();

        $this->pluginManager->update([], $this->client->getContainer());

        $order = $this->reload($order);
        $this->assertSame('charge-x', $order->getUnivapayChargeId());
        $this->assertSame(OrderStatus::PAID, $order->getOrderStatus()->getId());
        $this->assertSame(
            'renamed by merchant',
            $this->entityManager->find(OrderStatus::class, UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE)->getName()
        );
    }

    private function migrate()
    {
        $this->pluginManager->migrateLegacySubscriptions($this->entityManager, function ($subscriptionId) {
            if ($subscriptionId === 'sub-c') {
                throw new \RuntimeException('API unavailable');
            }

            return $subscriptionId === 'sub-b' ? 'canceled' : 'current';
        });
    }

    private function createLegacyOrder($subscriptionId, $chargeId, $statusId)
    {
        $order = $this->createOrderWithStatus($this->legacyPayment, $statusId);
        $order->setUnivapaySubscriptionId($subscriptionId);
        $order->setUnivapayChargeId($chargeId);
        $this->entityManager->flush();

        return $order;
    }

    private function createOneTimeOrder($chargeId)
    {
        $creditCard = $this->entityManager->getRepository(Payment::class)->findOneBy(['method_class' => CreditCard::class]);
        $order = $this->createOrderWithStatus($creditCard, OrderStatus::PAID);
        $order->setUnivapayChargeId($chargeId);
        $this->entityManager->flush();

        return $order;
    }

    private function createOrderWithStatus(Payment $payment, $statusId)
    {
        $order = $this->createOrder($this->createCustomer());
        $order->setPayment($payment);
        $order->setPaymentMethod($payment->getMethod());
        $order->setOrderStatus($this->entityManager->find(OrderStatus::class, $statusId));
        $this->entityManager->flush();

        return $order;
    }

    private function reload(Order $order)
    {
        $this->entityManager->clear();

        return $this->entityManager->find(Order::class, $order->getId());
    }

    private function assertParent(Order $order, $subscriptionId, $statusId)
    {
        $order = $this->reload($order);
        $this->assertSame($subscriptionId, $order->getUnivapaySubscriptionId());
        $this->assertNull($order->getUnivapayChargeId());
        $this->assertSame($statusId, $order->getOrderStatus()->getId());
        $this->assertLegacyPaymentReplaced($order);
    }

    private function assertChild(Order $order, $subscriptionId, $chargeId, $statusId)
    {
        $order = $this->reload($order);
        $this->assertSame($subscriptionId, $order->getUnivapaySubscriptionId());
        $this->assertSame($chargeId, $order->getUnivapayChargeId());
        $this->assertSame($statusId, $order->getOrderStatus()->getId());
        $this->assertLegacyPaymentReplaced($order);
    }

    private function assertLegacyPaymentReplaced(Order $order)
    {
        $this->assertSame(CreditCard::class, $order->getPayment()->getMethodClass());
        $this->assertSame(Constants::UNIVAPAY_PAYMENT_METHOD, $order->getPaymentMethod());
    }
}
