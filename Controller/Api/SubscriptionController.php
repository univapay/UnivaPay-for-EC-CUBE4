<?php

namespace Plugin\UnivaPay\Controller\Api;

use Eccube\Controller\AbstractController;
use Eccube\Entity\Order;
use Eccube\Repository\OrderRepository;
use Eccube\Service\MailService;
use Plugin\UnivaPay\Entity\Config;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;
use Plugin\UnivaPay\Repository\ConfigRepository;
use Plugin\UnivaPay\Service\SubscriptionOrderCreator;
use Plugin\UnivaPay\Util\ChargeOrderDuplicationPrevention;
use Plugin\UnivaPay\Util\SDK;
use Plugin\UnivaPay\Util\SubscriptionMetadata;
use Plugin\UnivaPay\Util\UnivaPayLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SubscriptionController extends AbstractController
{
    private $Config;
    private $Order;
    private $mailService;

    private $orderCreator;

    public function __construct(
        ConfigRepository $configRepository,
        OrderRepository $orderRepository,
        MailService $mailService,
        SubscriptionOrderCreator $orderCreator,
    ) {
        $this->Config = $configRepository;
        $this->Order = $orderRepository;
        $this->mailService = $mailService;
        $this->orderCreator = $orderCreator;
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
                    return $this->orderCreator->create(
                        $parent,
                        $charge,
                        $data->data->amount,
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

        // Refreshing the parent metadata after recording/ack-ing the charge
        // A lookup failure does not and should not undo the created order
        try {
            $parent = $this->Order->findOneBy([
                'univa_pay_parent_subscription_id' => $data->data->id,
                'univa_pay_charge_id' => null,
                'OrderStatus' => [
                    UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE,
                    UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_CANCEL,
                    UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_SUSPEND,
                ],
            ]);

            if ($parent) {
                SubscriptionMetadata::update(
                    $parent,
                    $sdk->getSubscription($data->data->id)
                );
                $this->entityManager->flush();
            }
        } catch (\Throwable $e) {
            UnivaPayLogger::get()->warning(
                'Subscription list metadata refresh failed',
                [
                    'subscription_id' => $data->data->id,
                    'error' => $e->getMessage(),
                ]
            );
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
}
