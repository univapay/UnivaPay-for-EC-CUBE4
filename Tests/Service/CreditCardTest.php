<?php

namespace Plugin\UnivaPay\Tests\Service;

use Eccube\Entity\Master\OrderStatus;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Eccube\Tests\EccubeTestCase;
use Plugin\UnivaPay\Entity\Config;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;
use Plugin\UnivaPay\Service\Method\CreditCard;

class CreditCardTest extends EccubeTestCase
{
    public function testSubscriptionParentEarnsNoPoints()
    {
        $Order = $this->createOrder($this->createCustomer());
        $Order->setUnivapayParentSubscriptionId($this->getFaker()->uuid());
        $Order->setAddPoint(100);

        $creditCard = new CreditCard(
            $this->entityManager->getRepository(OrderStatus::class),
            $this->createMock(PurchaseFlow::class),
            $this->entityManager->getRepository(Config::class)
        );
        $creditCard->setOrder($Order);

        $this->assertTrue($creditCard->checkout()->isSuccess());
        $this->assertSame(UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE, $Order->getOrderStatus()->getId());
        $this->assertEquals(0, $Order->getAddPoint());
    }
}
