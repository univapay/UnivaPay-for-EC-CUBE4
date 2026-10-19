<?php

namespace Plugin\UnivaPay\Tests\EventListener;

use Eccube\Entity\Order;
use Eccube\Entity\Payment;
use PHPUnit\Framework\TestCase;
use Plugin\UnivaPay\EventListener\OrderEventListener;
use Plugin\UnivaPay\EventListener\SubscriptionEventListener;
use Plugin\UnivaPay\Repository\ConfigRepository;
use Plugin\UnivaPay\Service\Method\CreditCard;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
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
        $listener = new OrderEventListener($this->configRepositoryNeverCalled(), $this->createMock(SessionInterface::class));
        $event = $this->createEvent($this->univaPayOrderWithoutIds());

        $listener->onPayOrder($event);
        $listener->onCancelOrder($event);
    }

    public function testSubscriptionTransitionsSkipUnivaPayOrderWithoutSubscription()
    {
        $listener = (new \ReflectionClass(SubscriptionEventListener::class))->newInstanceWithoutConstructor();
        $property = new \ReflectionProperty(SubscriptionEventListener::class, 'configRepository');
        $property->setAccessible(true);
        $property->setValue($listener, $this->configRepositoryNeverCalled());
        $event = $this->createEvent($this->univaPayOrderWithoutIds());

        $listener->onSuspendSubscription($event);
        $listener->onCancelSubscription($event);
        $listener->onResumeSubscription($event);
    }
}
