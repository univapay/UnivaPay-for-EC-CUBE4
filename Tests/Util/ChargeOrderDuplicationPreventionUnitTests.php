<?php



namespace Plugin\UnivaPay\Tests\Util;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\OrderRepository;
use PHPUnit\Framework\TestCase;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;
use Plugin\UnivaPay\Util\ChargeOrderDuplicationPrevention;

class ChargeOrderDuplicationPreventionTest extends TestCase
{
    public function invalidIds()
    {
        return [
            ['', 'subscriptionAssociatedChargeId'],
            ['subscriptionId', ''],
        ];
    }

    /** @dataProvider invalidIds */
    public function testRejectsEmptyIds($subscriptionId, $chargeId)
    {
        $repository = $this->createMock(OrderRepository::class);
        $repository->expects($this->never())
            ->method('findOneBy');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())
            ->method('transactional');

        $this->expectException(\InvalidArgumentException::class);

        (new ChargeOrderDuplicationPrevention($em, $repository))
            ->createOnce(
                $subscriptionId,
                $chargeId,
                function () {
                    $this->fail('Must not create an order');
                }
            );
    }

    public function testExistingChargeSkipsParentLookupLockAndCreation()
    {
        $existing = $this->chargeOrder('subscriptionAssociatedChargeId');
        $repository = $this->repository(null, [$existing], 1);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('transactional');
        $em->expects($this->never())->method('refresh');

        $result = (new ChargeOrderDuplicationPrevention($em, $repository))
            ->createOnce(
                'subscriptionId',
                'subscriptionAssociatedChargeId',
                function () {
                    $this->fail('Must not create an order');
                }
            );

        $this->assertSame([false, $existing], $result);
    }

    public function testMissingParentSkipsCreation()
    {
        $repository = $this->repository(null, [null], 2);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('transactional');

        $result = (new ChargeOrderDuplicationPrevention($em, $repository))
            ->createOnce(
                'subscriptionId',
                'subscriptionAssociatedChargeId',
                function () {
                    $this->fail('Must not create an order');
                }
            );

        $this->assertSame([false, null], $result);
    }

    public function testCreatesOnlyAfterLockAndSecondLookup()
    {
        $parent = $this->parent(
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE
        );

        $child = $this->chargeOrder('subscriptionAssociatedChargeId');
        $locked = false;

        $repository = $this->repository(
            $parent,
            [null, null],
            3,
            function () use (&$locked) {
                $this->assertTrue(
                    $locked,
                    'Recheck must happen after the parent lock'
                );
            }
        );

        $em = $this->transactionalEntityManager(
            $parent,
            function () use (&$locked) {
                $locked = true;
            }
        );

        $createdCount = 0;

        $result = (new ChargeOrderDuplicationPrevention($em, $repository))
            ->createOnce(
                'subscriptionId',
                'subscriptionAssociatedChargeId',
                function (Order $lockedParent) use (
                    $parent,
                    $child,
                    &$createdCount,
                    &$locked
                ) {
                    $this->assertTrue($locked);
                    $this->assertSame($parent, $lockedParent);

                    ++$createdCount;

                    return $child;
                }
            );

        $this->assertSame([true, $child], $result);
        $this->assertSame(1, $createdCount);
    }

    public function testSecondLookupFindsConcurrentOrderAndSkipsCreation()
    {
        $parent = $this->parent(
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE
        );

        $existing = $this->chargeOrder('subscriptionAssociatedChargeId');

        $repository = $this->repository(
            $parent,
            [null, $existing],
            3
        );

        $em = $this->transactionalEntityManager($parent);

        $result = (new ChargeOrderDuplicationPrevention($em, $repository))
            ->createOnce(
                'subscriptionId',
                'subscriptionAssociatedChargeId',
                function () {
                    $this->fail('Must not create an order');
                }
            );

        $this->assertSame([false, $existing], $result);
    }

    public function testInactiveParentAfterRefreshSkipsCreation()
    {
        $active = $this->status(
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE
        );

        $inactive = $this->status(
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_CANCEL
        );

        $isActive = true;

        $parent = $this->createMock(Order::class);

        $parent->method('getOrderStatus')
            ->willReturnCallback(
                function () use (&$isActive, $active, $inactive) {
                    return $isActive ? $active : $inactive;
                }
            );

        $repository = $this->repository(
            $parent,
            [null, null],
            3
        );

        $em = $this->transactionalEntityManager(
            $parent,
            function () use (&$isActive) {
                $isActive = false;
            }
        );

        $result = (new ChargeOrderDuplicationPrevention($em, $repository))
            ->createOnce(
                'subscriptionId',
                'subscriptionAssociatedChargeId',
                function () {
                    $this->fail('Must not create an order');
                }
            );

        $this->assertSame([false, null], $result);
    }

    public function testRejectsCreatedOrderWithWrongChargeId()
    {
        $parent = $this->parent(
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE
        );

        $repository = $this->repository(
            $parent,
            [null, null],
            3
        );

        $em = $this->transactionalEntityManager($parent);

        $this->expectException(\LogicException::class);

        (new ChargeOrderDuplicationPrevention($em, $repository))
            ->createOnce(
                'subscriptionId',
                'subscriptionAssociatedChargeId',
                function () {
                    return $this->chargeOrder('wrong-charge');
                }
            );
    }

    public function testRejectsNonOrderFromCreator()
    {
        $parent = $this->parent(
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE
        );

        $repository = $this->repository(
            $parent,
            [null, null],
            3
        );

        $em = $this->transactionalEntityManager($parent);

        $this->expectException(\LogicException::class);

        (new ChargeOrderDuplicationPrevention($em, $repository))
            ->createOnce(
                'subscriptionId',
                'subscriptionAssociatedChargeId',
                function () {
                    return null;
                }
            );
    }

    private function repository(
        $parent,
        array $chargeResults,
        $expectedQueries,
        ?callable $beforeRecheck = null,
    ): OrderRepository {
        $repository = $this->createMock(OrderRepository::class);
        $chargeLookups = 0;

        $repository->expects($this->exactly($expectedQueries))
            ->method('findOneBy')
            ->willReturnCallback(
                function ($criteria) use (
                    $parent,
                    $chargeResults,
                    &$chargeLookups,
                    $beforeRecheck
                ) {
                    if (array_key_exists('univa_pay_charge_id', $criteria)) {
                        $this->assertSame(
                            ['univa_pay_charge_id' => 'subscriptionAssociatedChargeId'],
                            $criteria
                        );

                        if ($chargeLookups > 0 && $beforeRecheck) {
                            $beforeRecheck();
                        }

                        return $chargeResults[$chargeLookups++];
                    }

                    $this->assertSame([
                        'univa_pay_parent_subscription_id' => 'subscriptionId',
                        'OrderStatus' => UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE,
                    ], $criteria);

                    return $parent;
                }
            );

        return $repository;
    }

    private function transactionalEntityManager(
        Order $parent,
        ?callable $onRefresh = null,
    ): EntityManagerInterface {
        $em = $this->createMock(EntityManagerInterface::class);

        $em->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function ($work) use ($em) {
                return $work($em);
            });

        $em->expects($this->once())
            ->method('refresh')
            ->with($parent, LockMode::PESSIMISTIC_WRITE)
            ->willReturnCallback(function () use ($onRefresh) {
                if ($onRefresh) {
                    $onRefresh();
                }
            });

        return $em;
    }

    private function parent($statusId): Order
    {
        $parent = $this->createMock(Order::class);

        $parent->method('getOrderStatus')
            ->willReturn($this->status($statusId));

        return $parent;
    }

    private function status($id): OrderStatus
    {
        $status = $this->createMock(OrderStatus::class);
        $status->method('getId')->willReturn($id);

        return $status;
    }

    // The duplication prevention guard only needs an Order that returns a particular Charge ID
    // Below is simplistic representation of such
    private function chargeOrder($chargeId): Order
    {
        return new class($chargeId) extends Order {
            private $chargeId;

            public function __construct($chargeId)
            {
                $this->chargeId = $chargeId;
            }

            public function getUnivapayChargeId()
            {
                return $this->chargeId;
            }
        };
    }
}
