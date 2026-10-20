<?php

namespace Plugin\UnivaPay\Controller\Api;

use Doctrine\DBAL\LockMode;
use Eccube\Controller\AbstractController;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Service\MailService;
use Eccube\Service\OrderHelper;
use Eccube\Service\PurchaseFlow\Processor\AddPointProcessor;
use Eccube\Service\PurchaseFlow\Processor\CustomerPurchaseInfoProcessor;
use Eccube\Service\PurchaseFlow\Processor\OrderNoProcessor;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Plugin\UnivaPay\Entity\Config;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;
use Plugin\UnivaPay\Repository\ConfigRepository;
use Plugin\UnivaPay\Util\OrderCopier;
use Plugin\UnivaPay\Util\SDK;
use Plugin\UnivaPay\Util\UnivaPayLogger;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionController extends AbstractController
{
    /** @var AddPointProcessor */
    private $addPointProcessor;

    /** @var ConfigRepository */
    private $Config;

    /** @var OrderRepository */
    private $Order;

    /** @var OrderStatusRepository */
    private $orderStatusRepository;

    /** @var CustomerPurchaseInfoProcessor */
    private $customerPurchaseInfoProcessor;

    /** @var OrderHelper */
    private $orderHelper;

    /** @var OrderNoProcessor */
    private $orderNoProcessor;

    /** @var MailService */
    private $mailService;

    public function __construct(
        AddPointProcessor $addPointProcessor,
        ConfigRepository $configRepository,
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository,
        CustomerPurchaseInfoProcessor $customerPurchaseInfoProcessor,
        OrderHelper $orderHelper,
        OrderNoProcessor $orderNoProcessor,
        MailService $mailService
    ) {
        $this->Config = $configRepository;
        $this->Order = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->customerPurchaseInfoProcessor = $customerPurchaseInfoProcessor;
        $this->orderHelper = $orderHelper;
        $this->orderNoProcessor = $orderNoProcessor;
        $this->addPointProcessor = $addPointProcessor;
        $this->mailService = $mailService;
    }

    /**
     * subscription webhook action
     *
     * @Route("/univapay/hook", name="univa_pay_hook", methods={"POST"})
     */
    public function hook(Request $request)
    {
        $config = $this->Config->findOneById(1);

        if (!$this->isHeaderAuthValid($request, $config)) {
            return new Response(trans('univa_pay.error.webhook.authorization'), 401);
        }

        $data = json_decode($request->getContent());
        if (!in_array($data->event, ['subscription_payment', 'subscription_failure'], true)) {
            return new Response();
        }

        $util = new SDK($config);

        $charge = $util->getLatestChargeBySubscriptionId($data->data->id);
        if (is_null($charge)) {
            return new Response('Charge not found', 404);
        }

        if ($this->Order->findOneBy(['univa_pay_charge_id' => $charge->getId()])) {
            return new Response('Order already created', 200);
        }

        $subscriptionOrder = $this->Order->findOneBy([
            'univa_pay_parent_subscription_id' => $data->data->id,
            'OrderStatus' => UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE,
        ]);

        if (is_null($subscriptionOrder)) {
            return new Response(trans('univa_pay.error.webhook.subscription.not_found'), 404);
        }

        $order = $this->createOrder($subscriptionOrder, $data, $charge, $data->event === 'subscription_payment');
        $util->updateChargeMetaData($order, $charge);

        return new Response('successfully accepted', 201);
    }

    private function isHeaderAuthValid(Request $request, Config $config): bool
    {
        if (empty($config->getWebhookAuth())) {
            return true;
        }

        $authHeader = $request->headers->get('Authorization');

        if ($config->getWebhookAuth() !== $authHeader) {
            UnivaPayLogger::get()->info('Invalid webhook authorization attempt', ['provided' => $authHeader]);

            return false;
        }

        return true;
    }

    /**
     * Copies the parent order into a renewal order. A failed renewal is recorded as a cancelled order without
     * touching stock or customer purchase totals.
     */
    private function createOrder(Order $existOrder, $data, $charge, bool $paid): Order
    {
        $newOrder = OrderCopier::copy($existOrder);
        $newOrder->setUnivapayChargeId($charge->id);
        // Renewal amount can differ from the first charge, so derive subtotal from it.
        $newOrder->setSubtotal($existOrder->getSubtotal() - $existOrder->getTotal() + $data->data->amount - $existOrder->getDiscount());
        $newOrder->setDiscount(0);
        $newOrder->setTotal($data->data->amount);
        $newOrder->setPaymentTotal($data->data->amount);
        $newOrder->setOrderDate(new \DateTime());
        $newOrder->setPaymentDate(new \DateTime());
        $newOrder->setPreOrderId($this->orderHelper->createPreOrderId());

        $purchaseContext = new PurchaseContext($newOrder, $newOrder->getCustomer());
        // EC-CUBE 4.0 needs add point recalculated before the order is saved.
        $this->addPointProcessor->validate($newOrder, $purchaseContext);
        if ($paid) {
            $this->reduceStock($newOrder);
            $this->customerPurchaseInfoProcessor->commit($newOrder, $purchaseContext);
        }
        $this->entityManager->persist($newOrder);
        // Order number formats may use the order id, so number it after the first flush.
        $this->entityManager->flush();
        $this->orderNoProcessor->process($newOrder, $purchaseContext);
        // Set directly, not via the state machine: its pay/cancel listeners would capture/refund a charge UnivaPay already settled.
        $newOrder->setOrderStatus($this->orderStatusRepository->find($paid ? OrderStatus::PAID : OrderStatus::CANCEL));
        $this->entityManager->flush();
        if ($paid) {
            $this->mailService->sendOrderMail($newOrder);
        }

        return $newOrder;
    }

    /**
     * Same as StockReduceProcessor but allows negative stock, since the renewal is already charged.
     * A subscription order holds one product (CartValidationEventListener).
     */
    private function reduceStock(Order $order)
    {
        $item = $order->getProductOrderItems()[0];
        $ProductClass = $item->getProductClass();
        if ($ProductClass->isStockUnlimited()) {
            return;
        }
        $ProductStock = $ProductClass->getProductStock();
        $this->entityManager->lock($ProductStock, LockMode::PESSIMISTIC_WRITE);
        $this->entityManager->refresh($ProductStock);
        $stock = $ProductStock->getStock() - $item->getQuantity();
        $ProductStock->setStock($stock);
        $ProductClass->setStock($stock);
        if ($stock < 0) {
            UnivaPayLogger::get()->warning('Subscription renewal reduced stock below zero', [
                'parent_subscription_id' => $order->getUnivapayParentSubscriptionId(),
                'product_class_id' => $ProductClass->getId(),
                'stock' => $stock,
            ]);
        }
    }
}
