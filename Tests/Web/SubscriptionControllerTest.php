<?php

namespace Plugin\UnivaPay\Tests\Web;

require_once __DIR__.'/../../Resource/autoload.php';

use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Tests\EccubeTestCase;
use Plugin\UnivaPay\Service\SubscriptionOrderCreator;
use UnivaPay\Models\Charge;

class SubscriptionControllerTest extends EccubeTestCase
{
    private function createParentOrderWithoutStock(): Order
    {
        $Order = $this->createOrder($this->createCustomer());
        $Order->setUnivapayParentSubscriptionId($this->getFaker()->uuid());
        foreach ($Order->getProductOrderItems() as $item) {
            $ProductClass = $item->getProductClass();
            $ProductClass->setStockUnlimited(false);
            $ProductClass->setStock(0);
            $ProductClass->getProductStock()->setStock(0);
        }
        $this->entityManager->flush();

        return $Order;
    }

    private function renew(Order $Parent, bool $paid): Order
    {
        $creator = static::getContainer()->get(SubscriptionOrderCreator::class);

        $charge = new Charge();
        $charge->setId($this->getFaker()->uuid());

        $event = $paid ? 'subscription_payment' : 'subscription_failure';

        return $creator->create($Parent, $charge, (int) $Parent->getPaymentTotal(), $event);
    }

    public function testPaymentWithoutStockCreatesPaidOrderAndNegativeStock()
    {
        $Parent = $this->createParentOrderWithoutStock();

        $buyTimes = $Parent->getCustomer()->getBuyTimes();

        $Order = $this->renew($Parent, true);

        $this->assertEquals(OrderStatus::PAID, $Order->getOrderStatus()->getId());
        $this->assertNotNull($Order->getOrderNo());
        $this->assertEquals($buyTimes + 1, $Order->getCustomer()->getBuyTimes());
        foreach ($Order->getProductOrderItems() as $item) {
            $this->entityManager->refresh($item->getProductClass()->getProductStock());
            $this->assertEquals(-$item->getQuantity(), $item->getProductClass()->getProductStock()->getStock());
        }
    }

    public function testFailureKeepsStockAndPurchaseTotals()
    {
        $Parent = $this->createParentOrderWithoutStock();

        $buyTimes = $Parent->getCustomer()->getBuyTimes();

        $Order = $this->renew($Parent, false);

        $this->assertEquals(OrderStatus::CANCEL, $Order->getOrderStatus()->getId());
        $this->assertEquals($buyTimes, $Order->getCustomer()->getBuyTimes());
        foreach ($Order->getProductOrderItems() as $item) {
            $this->entityManager->refresh($item->getProductClass()->getProductStock());
            $this->assertEquals(0, $item->getProductClass()->getProductStock()->getStock());
        }
    }

    public function testOnlyFirstChargeCarriesUsedPoints()
    {
        $Parent = $this->createParentOrderWithoutStock();
        $Parent->setUsePoint(100);
        $Customer = $Parent->getCustomer();
        $Customer->setPoint(500);
        $this->entityManager->flush();

        $First = $this->renew($Parent, true);
        $this->assertEquals(100, $First->getUsePoint());
        $this->assertEquals(400, $Customer->getPoint());

        $Second = $this->renew($Parent, true);
        $this->assertEquals(0, $Second->getUsePoint());
        $this->assertEquals(0, $Second->getDiscount());
        $this->assertEquals(400, $Customer->getPoint());
    }
}
