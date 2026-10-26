<?php

namespace Plugin\UnivaPay\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\OrderHelper;
use Eccube\Service\PurchaseFlow\Processor\AddPointProcessor;
use Eccube\Service\PurchaseFlow\Processor\OrderNoProcessor;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use PHPUnit\Framework\TestCase;
use Plugin\UnivaPay\Service\SubscriptionOrderCreator;
use UnivaPay\Models\Charge;

class SubscriptionOrderCreatorTest extends TestCase
{
    private $em;
    private $statuses;
    private $flow;
    private $helper;
    private $orderNo;
    private $points;

    protected function setUp(): void
    {
        parent::setUp();

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->statuses = $this->createMock(OrderStatusRepository::class);
        $this->flow = $this->createMock(PurchaseFlow::class);
        $this->helper = $this->createMock(OrderHelper::class);
        $this->orderNo = $this->createMock(OrderNoProcessor::class);
        $this->points = $this->createMock(AddPointProcessor::class);
    }

    public static function paymentEvents(): array
    {
        return [
            'successful charge' => [
                'subscription_payment',
                OrderStatus::PAID,
            ],
            'failed charge' => [
                'subscription_failure',
                OrderStatus::CANCEL,
            ],
        ];
    }

    /**
     * @dataProvider paymentEvents
     */
    public function testCreatesChildOrderAndRunsPurchaseFlow(
        string $event,
        int $expectedStatusId,
    ): void {
        $parent = $this->parentOrder();
        $charge = $this->charge('subscriptionAssociatedChargeId');
        $status = $this->createMock(OrderStatus::class);

        $this->statuses->expects($this->once())
            ->method('find')
            ->with($expectedStatusId)
            ->willReturn($status);

        $this->points->expects($this->once())
            ->method('validate')
            ->with(
                $this->isInstanceOf(Order::class),
                $this->isInstanceOf(PurchaseContext::class)
            );

        $this->helper->expects($this->once())
            ->method('createPreOrderId')
            ->willReturn('preOrderId');

        $this->flow->expects($this->once())
            ->method('prepare')
            ->with(
                $this->isInstanceOf(Order::class),
                $this->isInstanceOf(PurchaseContext::class)
            );

        $this->flow->expects($this->once())
            ->method('commit')
            ->with(
                $this->isInstanceOf(Order::class),
                $this->isInstanceOf(PurchaseContext::class)
            );

        $persistedOrder = null;

        $this->em->expects($this->once())
            ->method('persist')
            ->with(
                $this->callback(function ($order) use (&$persistedOrder) {
                    $persistedOrder = $order;

                    return $order instanceof Order;
                })
            );

        $this->em->expects($this->exactly(2))
            ->method('flush');

        $this->orderNo->expects($this->once())
            ->method('process')
            ->with(
                $this->isInstanceOf(Order::class),
                $this->isInstanceOf(PurchaseContext::class)
            );

        $child = $this->creator()->create(
            $parent,
            $charge,
            1200,
            $event
        );

        // Verify the newly created order.
        $this->assertNotSame($parent, $child);
        $this->assertSame($persistedOrder, $child);

        $this->assertSame(
            'subscriptionId',
            $child->getUnivapayParentSubscriptionId()
        );

        $this->assertSame(
            'subscriptionAssociatedChargeId',
            $child->getUnivapayChargeId()
        );

        $this->assertEquals(1200, $child->getSubtotal());
        $this->assertEquals(0, $child->getDiscount());
        $this->assertEquals(1200, $child->getTotal());
        $this->assertEquals(1200, $child->getPaymentTotal());

        $this->assertSame(
            'preOrderId',
            $child->getPreOrderId()
        );

        $this->assertSame($status, $child->getOrderStatus());

        $this->assertInstanceOf(
            \DateTimeInterface::class,
            $child->getPaymentDate()
        );

        // Verify that the original parent order is unchanged.
        $this->assertNull($parent->getUnivapayChargeId());
        $this->assertEquals(1000, $parent->getTotal());
        $this->assertEquals(1100, $parent->getSubtotal());
        $this->assertEquals(100, $parent->getDiscount());
    }

    public function testRejectsUnsupportedEventWithoutSideEffects(): void
    {
        $this->expectNoSideEffects();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Unsupported subscription event'
        );

        $this->creator()->create(
            $this->parentOrder(),
            $this->charge('subscriptionAssociatedChargeId'),
            1200,
            'charge_updated'
        );
    }

    public function testRejectsNegativeAmountWithoutSideEffects(): void
    {
        $this->expectNoSideEffects();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Invalid subscription charge'
        );

        $this->creator()->create(
            $this->parentOrder(),
            $this->charge('subscriptionAssociatedChargeId'),
            -1,
            'subscription_payment'
        );
    }

    public function testRejectsMissingChargeIdWithoutSideEffects(): void
    {
        $this->expectNoSideEffects();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Invalid subscription charge'
        );

        $this->creator()->create(
            $this->parentOrder(),
            new Charge(),
            1200,
            'subscription_payment'
        );
    }

    public function testMissingOrderStatusThrowsAnException(): void
    {
        $this->statuses->expects($this->once())
            ->method('find')
            ->with(OrderStatus::PAID)
            ->willReturn(null);

        // Current implementation persists before status lookup.
        $this->em->expects($this->once())
            ->method('persist');

        $this->em->expects($this->once())
            ->method('flush');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Subscription order status not found'
        );

        $this->creator()->create(
            $this->parentOrder(),
            $this->charge('subscriptionAssociatedChargeId'),
            1200,
            'subscription_payment'
        );
    }

    public function testPurchaseFlowFailureStopsBeforePersistence(): void
    {
        $this->flow->expects($this->once())
            ->method('prepare')
            ->willThrowException(
                new \RuntimeException('prepare failed')
            );

        $this->flow->expects($this->never())
            ->method('commit');

        $this->em->expects($this->never())
            ->method('persist');

        $this->em->expects($this->never())
            ->method('flush');

        $this->orderNo->expects($this->never())
            ->method('process');

        $this->statuses->expects($this->never())
            ->method('find');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('prepare failed');

        $this->creator()->create(
            $this->parentOrder(),
            $this->charge('subscriptionAssociatedChargeId'),
            1200,
            'subscription_payment'
        );
    }

    private function expectNoSideEffects(): void
    {
        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $this->flow->expects($this->never())->method('prepare');
        $this->flow->expects($this->never())->method('commit');

        $this->points->expects($this->never())->method('validate');

        $this->orderNo->expects($this->never())->method('process');

        $this->helper->expects($this->never())
            ->method('createPreOrderId');

        $this->statuses->expects($this->never())->method('find');
    }

    private function parentOrder(): Order
    {
        if (!method_exists(
            Order::class,
            'setUnivapayParentSubscriptionId'
        )) {
            $this->markTestSkipped(
                'UnivaPay OrderTrait is not loaded. '
                    .'EC-CUBE generated entity proxies are required.'
            );
        }

        $parent = new Order();

        $parent->setUnivapayParentSubscriptionId(
            'subscriptionId'
        );

        $parent->setSubtotal(1100);
        $parent->setTotal(1000);
        $parent->setDiscount(100);

        return $parent;
    }

    private function charge(string $id): Charge
    {
        $charge = new Charge();
        $charge->setId($id);

        return $charge;
    }

    private function creator(): SubscriptionOrderCreator
    {
        return new SubscriptionOrderCreator(
            $this->em,
            $this->statuses,
            $this->flow,
            $this->helper,
            $this->orderNo,
            $this->points
        );
    }
}
