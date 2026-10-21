<?php

namespace Plugin\UnivaPay\Util;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Eccube\Entity\Order;
use Eccube\Repository\OrderRepository;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;

class ChargeOrderDuplicationPrevention
{
    private $entityManager;
    private $orders;

    public function __construct(
        EntityManagerInterface $entityManager,
        OrderRepository $orders
    ) {
        $this->entityManager = $entityManager;
        $this->orders = $orders;
    }

    /**
     * @return array [bool $created, ?Order $order]
     */
    public function createOnce(
        string $subscriptionId,
        string $chargeId,
        callable $create
    ): array {
        if ($subscriptionId === '' || $chargeId === '') {
            throw new \InvalidArgumentException(
                'Subscription ID and Charge ID are required'
            );
        }

        // Simplistic check => If there's existing order ( possible dups )?
        // => Exit without taking the lock
        // => As in, not participating in creation
        $existing = $this->orders->findOneBy([
            'univa_pay_charge_id' => $chargeId,
        ]);

        if ($existing) {
            return [false, $existing];
        }

        // Subscription parent => Shared lock target
        $parent = $this->orders->findOneBy([
            'univa_pay_parent_subscription_id' => $subscriptionId,
            'OrderStatus' =>
            UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE,
        ]);

        if (!$parent) {
            return [false, null];
        }

        return $this->entityManager->transactional(
            function (EntityManagerInterface $em) use (
                $parent,
                $chargeId,
                $create
            ) {
                // Lock and refresh this specific EC-CUBE parent Order
                $em->refresh(
                    $parent,
                    LockMode::PESSIMISTIC_WRITE
                );

                // Recheck again to prevent staleness after acquiring the lock, as a prevention
                $existing = $this->orders->findOneBy([
                    'univa_pay_charge_id' => $chargeId,
                ]);

                if ($existing) {
                    return [false, $existing];
                }

                // Is subscription still active?
                if (
                    !$parent->getOrderStatus() ||
                    (int) $parent->getOrderStatus()->getId()
                    !== UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE
                ) {
                    return [false, null];
                }

                $order = $create($parent);

                if (
                    !$order instanceof Order ||
                    $order->getUnivapayChargeId() !== $chargeId
                ) {
                    throw new \LogicException(
                        'Created order has an unexpected Charge ID'
                    );
                }

                return [true, $order];
            }
        );
    }
}
