<?php

namespace Plugin\UnivaPay\Service;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Service\OrderHelper;
use Eccube\Service\PurchaseFlow\Processor\AddPointProcessor;
use Eccube\Service\PurchaseFlow\Processor\OrderNoProcessor;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Plugin\UnivaPay\Util\OrderCopier;
use UnivaPay\Models\Charge;

class SubscriptionOrderCreator
{
    private $entityManager;
    private $orderStatusRepository;
    private $purchaseFlow;
    private $orderHelper;
    private $orderNoProcessor;
    private $addPointProcessor;
    private $orderRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        OrderStatusRepository $orderStatusRepository,
        PurchaseFlow $shoppingPurchaseFlow,
        OrderHelper $orderHelper,
        OrderNoProcessor $orderNoProcessor,
        AddPointProcessor $addPointProcessor,
        OrderRepository $orderRepository,
    ) {
        $this->entityManager = $entityManager;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->orderHelper = $orderHelper;
        $this->orderNoProcessor = $orderNoProcessor;
        $this->addPointProcessor = $addPointProcessor;
        $this->orderRepository = $orderRepository;
    }

    public function create(
        Order $parent,
        Charge $charge,
        int $amount,
        string $event,
    ): Order {
        if (!in_array(
            $event,
            ['subscription_payment', 'subscription_failure'],
            true
        )) {
            throw new \InvalidArgumentException('Unsupported subscription event');
        }

        if ($amount < 0 || !$charge->getId()) {
            throw new \InvalidArgumentException('Invalid subscription charge');
        }

        // Points and discounts used at checkout apply to the first charge only.
        $isFirstCharge = $this->isFirstCharge($parent);
        $newOrder = OrderCopier::copy($parent, $isFirstCharge);

        // Associate the recurring charge with the new order.
        $newOrder->setUnivapayChargeId($charge->getId());

        if (!$isFirstCharge) {
            // Retain the plugin's existing accounting calculation.
            $newOrder->setSubtotal(
                $parent->getSubtotal()
                - $parent->getTotal()
                + $amount
                - $parent->getDiscount()
            );
        }

        $newOrder->setTotal($amount);
        $newOrder->setPaymentTotal($amount);
        $newOrder->setPaymentDate(new \DateTime());

        // Run EC-CUBE's purchase flow.
        $context = new PurchaseContext(
            $newOrder,
            $newOrder->getCustomer()
        );

        $this->addPointProcessor->validate(
            $newOrder,
            $context
        );

        $newOrder->setPreOrderId(
            $this->orderHelper->createPreOrderId()
        );

        if ($event === 'subscription_payment') {
            $this->purchaseFlow->prepare(
                $newOrder,
                $context
            );

            $this->purchaseFlow->commit(
                $newOrder,
                $context
            );
        } else {
            $newOrder->setOrderDate(new \DateTime());
        }

        // Save the new order.
        $this->entityManager->persist($newOrder);
        $this->entityManager->flush();

        // Generate the order number.
        $this->orderNoProcessor->process(
            $newOrder,
            $context
        );

        // Set order status according to webhook event.
        $statusId = $event === 'subscription_payment'
            ? OrderStatus::PAID
            : OrderStatus::CANCEL;

        $status = $this->orderStatusRepository->find($statusId);

        if (!$status) {
            throw new \RuntimeException('Subscription order status not found: '.$statusId);
        }

        $newOrder->setOrderStatus($status);

        $this->entityManager->flush();

        return $newOrder;
    }

    private function isFirstCharge(Order $parent): bool
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('univa_pay_parent_subscription_id', $parent->getUnivapayParentSubscriptionId()))
            ->andWhere(Criteria::expr()->neq('univa_pay_charge_id', null))
            ->andWhere(Criteria::expr()->neq('OrderStatus', $this->entityManager->getReference(OrderStatus::class, OrderStatus::CANCEL)));

        return $this->orderRepository->matching($criteria)->isEmpty();
    }
}
