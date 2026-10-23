<?php

namespace Plugin\UnivaPay\Tests\Web;

use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Tests\EccubeTestCase;
use Plugin\UnivaPay\Controller\Api\SubscriptionController;

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
        // Order mail template reads BaseInfo, a Twig global normally set by the request listener.
        self::$container->get('twig')->addGlobal('BaseInfo', self::$container->get(BaseInfoRepository::class)->get());
        $controller = self::$container->get(SubscriptionController::class);
        $method = new \ReflectionMethod($controller, 'createOrder');
        $method->setAccessible(true);
        $data = (object) ['data' => (object) ['amount' => $Parent->getPaymentTotal()]];
        $charge = (object) ['id' => $this->getFaker()->uuid()];

        return $method->invoke($controller, $Parent, $data, $charge, $paid);
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
}
