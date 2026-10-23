<?php

namespace Plugin\UnivaPay\Controller\Api;

use Eccube\Controller\AbstractController;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Service\MailService;
use Eccube\Service\OrderHelper;
use Eccube\Service\PurchaseFlow\Processor\AddPointProcessor;
use Eccube\Service\PurchaseFlow\Processor\OrderNoProcessor;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Plugin\UnivaPay\Entity\Config;
use Plugin\UnivaPay\Repository\ConfigRepository;
use Plugin\UnivaPay\Util\ChargeOrderDuplicationPrevention;
use Plugin\UnivaPay\Util\OrderCopier;
use Plugin\UnivaPay\Util\SDK;
use Plugin\UnivaPay\Util\UnivaPayLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SubscriptionController extends AbstractController
{
    private $addPointProcessor;
    private $Config;
    private $Order;
    private $orderStatusRepository;
    private $purchaseFlow;
    private $orderHelper;
    private $orderNoProcessor;
    private $mailService;

    public function __construct(
        AddPointProcessor $addPointProcessor,
        ConfigRepository $configRepository,
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository,
        PurchaseFlow $shoppingPurchaseFlow,
        OrderHelper $orderHelper,
        OrderNoProcessor $orderNoProcessor,
        MailService $mailService,
    ) {
        $this->addPointProcessor = $addPointProcessor;
        $this->Config = $configRepository;
        $this->Order = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->orderHelper = $orderHelper;
        $this->orderNoProcessor = $orderNoProcessor;
        $this->mailService = $mailService;
    }

    /**
     * Subscription webhook action.
     *
     * @Route("/univapay/hook", name="univa_pay_hook", methods={"POST"})
     */
    public function hook(Request $request)
    {
        try {
            $config = $this->Config->get(1);

            if (!$config) {
                UnivaPayLogger::get()->error(
                    'Webhook ignored: UnivaPay config not found'
                );

                return new Response('Acknowledged', 200);
            }

            if (!$this->isHeaderAuthValid($request, $config)) {
                UnivaPayLogger::get()->warning(
                    'Webhook ignored: invalid authorization'
                );

                return new Response('Acknowledged', 200);
            }

            $data = json_decode($request->getContent());

            if (
                !is_object($data)
                || !isset($data->event)
                || !is_string($data->event)
            ) {
                return new Response('Ignored: invalid payload', 200);
            }

            // This controller handles only subscription payment events.
            if (!in_array(
                $data->event,
                ['subscription_payment', 'subscription_failure'],
                true
            )) {
                return new Response('Ignored: unrelated event', 200);
            }

            if (
                !isset($data->data->id, $data->data->amount)
                || !is_string($data->data->id)
                || $data->data->id === ''
                || !is_int($data->data->amount)
                || $data->data->amount < 0
            ) {
                UnivaPayLogger::get()->warning(
                    'Webhook ignored: invalid subscription payload'
                );

                return new Response('Acknowledged', 200);
            }

            $sdk = $this->createSdk($config);

            // Preserve the plugin's existing charge retrieval behavior.
            $charge = $sdk->getLatestChargeBySubscriptionId(
                $data->data->id
            );

            if (!$charge || !$charge->getId()) {
                UnivaPayLogger::get()->warning(
                    'Subscription charge not found',
                    ['subscription_id' => $data->data->id]
                );

                return new Response('Acknowledged', 200);
            }

            if ($charge->getSubscriptionId() !== $data->data->id) {
                UnivaPayLogger::get()->warning(
                    'Charge/subscription mismatch',
                    [
                        'subscription_id' => $data->data->id,
                        'charge_id' => $charge->getId(),
                    ]
                );

                return new Response('Acknowledged', 200);
            }

            // One shared guard for subscription order creation.
            $guard = new ChargeOrderDuplicationPrevention(
                $this->entityManager,
                $this->Order
            );

            list($created, $order) = $guard->createOnce(
                $data->data->id,
                $charge->getId(),
                function (Order $parent) use ($data, $charge) {
                    return $this->createOrder(
                        $parent,
                        $data,
                        $charge,
                        $data->event
                    );
                }
            );

            if (!$created) {
                if (!$order) {
                    UnivaPayLogger::get()->warning(
                        'Subscription parent not found or inactive',
                        [
                            'subscription_id' => $data->data->id,
                            'charge_id' => $charge->getId(),
                        ]
                    );
                }

                return new Response(
                    $order ? 'Order already created' : 'Acknowledged',
                    200
                );
            }
        } catch (\Throwable $e) {
            // Intentionally acknowledge the webhook.
            // The error must still be logged for store reconciliation.
            UnivaPayLogger::get()->error(
                'Subscription webhook processing failed',
                ['error' => $e->getMessage()]
            );

            return new Response('Acknowledged', 200);
        }

        // The order has now been committed.
        // Do not keep the database lock during external operations.
        try {
            $sdk->updateChargeMetaData($order, $charge);
        } catch (\Throwable $e) {
            UnivaPayLogger::get()->warning(
                'Charge metadata update failed',
                [
                    'order_id' => $order->getId(),
                    'error' => $e->getMessage(),
                ]
            );
        }

        // Only the request that created the order sends its mail.
        if ($data->event === 'subscription_payment') {
            try {
                $this->mailService->sendOrderMail($order);
            } catch (\Throwable $e) {
                UnivaPayLogger::get()->error(
                    'Subscription order mail failed',
                    [
                        'order_id' => $order->getId(),
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }

        return new Response('Successfully accepted', 200);
    }

    private function isHeaderAuthValid(Request $request, Config $config): bool
    {
        if (empty($config->getWebhookAuth())) {
            return true;
        }

        $authHeader = $request->headers->get('Authorization') || $request->headers->get('authorization');

        if ($config->getWebhookAuth() !== $authHeader) {
            UnivaPayLogger::get()->info('Invalid webhook authorization attempt', ['provided' => $authHeader]);

            return false;
        }

        return true;
    }

    /**
     * Allows SDK replacement in tests without a live connection.
     */
    protected function createSdk(Config $config): SDK
    {
        return new SDK($config);
    }

    /**
     * Create a recurring order using EC-CUBE's existing purchase flow.
     */
    private function createOrder(
        Order $existingOrder,
        $data,
        $charge,
        string $event,
    ): Order {
        $newOrder = OrderCopier::copy($existingOrder);

        // Current payment Charge ID.
        $newOrder->setUnivapayChargeId($charge->getId());

        // Calculate subtotal from the current payment amount.
        $newSubtotal = $existingOrder->getSubtotal()
            - $existingOrder->getTotal()
            + $data->data->amount
            - $existingOrder->getDiscount();

        $newOrder->setSubtotal($newSubtotal);

        // Disable previous discount.
        $newOrder->setDiscount(0);

        // Update amounts for this recurring payment.
        $newOrder->setTotal($data->data->amount);
        $newOrder->setPaymentTotal($data->data->amount);
        $newOrder->setPaymentDate(new \DateTime());

        $purchaseContext = new PurchaseContext(
            $newOrder,
            $newOrder->getCustomer()
        );

        // Existing EC-CUBE purchase processing.
        $this->addPointProcessor->validate(
            $newOrder,
            $purchaseContext
        );

        $newOrder->setPreOrderId(
            $this->orderHelper->createPreOrderId()
        );

        $this->purchaseFlow->prepare(
            $newOrder,
            $purchaseContext
        );

        $this->purchaseFlow->commit(
            $newOrder,
            $purchaseContext
        );

        $this->entityManager->persist($newOrder);
        $this->entityManager->flush();

        // Regenerate the order number.
        $this->orderNoProcessor->process(
            $newOrder,
            $purchaseContext
        );

        $this->entityManager->flush();

        // Set final status based on payment event.
        $status = $this->orderStatusRepository->find(
            $event === 'subscription_payment'
                ? OrderStatus::PAID
                : OrderStatus::CANCEL
        );

        $newOrder->setOrderStatus($status);
        $this->entityManager->flush();

        return $newOrder;
    }
}
