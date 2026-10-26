<?php



namespace Plugin\UnivaPay\Service;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
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

    public function __construct(
        EntityManagerInterface $entityManager,
        OrderStatusRepository $orderStatusRepository,
        PurchaseFlow $shoppingPurchaseFlow,
        OrderHelper $orderHelper,
        OrderNoProcessor $orderNoProcessor,
        AddPointProcessor $addPointProcessor,
    ) {
        $this->entityManager = $entityManager;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->orderHelper = $orderHelper;
        $this->orderNoProcessor = $orderNoProcessor;
        $this->addPointProcessor = $addPointProcessor;
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

        // Copy the subscription parent order.
        $newOrder = OrderCopier::copy($parent);

        // Associate the recurring charge with the new order.
        $newOrder->setUnivapayChargeId($charge->getId());

        // Retain the plugin's existing accounting calculation.
        $newOrder->setSubtotal(
            $parent->getSubtotal()
            - $parent->getTotal()
            + $amount
            - $parent->getDiscount()
        );

        $newOrder->setDiscount(0);
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

        $this->purchaseFlow->prepare(
            $newOrder,
            $context
        );

        $this->purchaseFlow->commit(
            $newOrder,
            $context
        );

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
}
