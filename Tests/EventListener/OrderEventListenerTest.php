<?php

namespace Plugin\UnivaPay\Tests\EventListener;

use Eccube\Entity\Order;
use Eccube\Entity\Payment;
use PHPUnit\Framework\TestCase;
use Plugin\UnivaPay\EventListener\OrderEventListener;
use Plugin\UnivaPay\Repository\ConfigRepository;
use Plugin\UnivaPay\Service\Method\CreditCard;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Workflow\Event\Event;

class OrderEventListenerTest extends TestCase
{
    private function createEvent(Order $Order)
    {
        $context = $this->getMockBuilder(\stdClass::class)->setMethods(['getOrder'])->getMock();
        $context->method('getOrder')->willReturn($Order);
        $event = $this->createMock(Event::class);
        $event->method('getSubject')->willReturn($context);

        return $event;
    }

    private function univaPayOrderWithoutIds()
    {
        $Payment = new Payment();
        $Payment->setMethodClass(CreditCard::class);
        $Order = new Order();
        $Order->setPayment($Payment);

        return $Order;
    }

    private function configRepositoryNeverCalled()
    {
        $configRepository = $this->createMock(ConfigRepository::class);
        $configRepository->expects($this->never())->method($this->anything());

        return $configRepository;
    }

    public function testPayAndCancelSkipUnivaPayOrderWithoutCharge()
    {
        $listener = new OrderEventListener($this->configRepositoryNeverCalled(), $this->createMock(RequestStack::class));
        $event = $this->createEvent($this->univaPayOrderWithoutIds());

        $listener->onPayOrder($event);
        $listener->onCancelOrder($event);
    }
}
