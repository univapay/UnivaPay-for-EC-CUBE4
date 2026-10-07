<?php

namespace Plugin\UnivaPay\Controller\Api;

use Eccube\Controller\AbstractController;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Service\MailService;
use Eccube\Service\OrderHelper;
use Eccube\Service\OrderStateMachine;
use Eccube\Service\PurchaseFlow\Processor\AddPointProcessor;
use Eccube\Service\PurchaseFlow\Processor\OrderNoProcessor;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Exception;
use Plugin\UnivaPay\Entity\Config;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;
use Plugin\UnivaPay\Repository\ConfigRepository;
use Plugin\UnivaPay\Util\SDK;
use Plugin\UnivaPay\Util\UnivaPayLogger;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Univapay\Enums\WebhookEvent;

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

    /** @var PurchaseFlow */
    private $purchaseFlow;

    /** @var OrderHelper */
    private $orderHelper;

    /** @var OrderNoProcessor */
    private $orderNoProcessor;

    /** @var MailService */
    private $mailService;

    /** @var OrderStateMachine */
    private $orderStateMachine;

    public function __construct(
        AddPointProcessor $addPointProcessor,
        ConfigRepository $configRepository,
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository,
        PurchaseFlow $shoppingPurchaseFlow,
        OrderHelper $orderHelper,
        OrderNoProcessor $orderNoProcessor,
        MailService $mailService,
        OrderStateMachine $orderStateMachine
    ) {
        $this->Config = $configRepository;
        $this->Order = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->orderHelper = $orderHelper;
        $this->orderNoProcessor = $orderNoProcessor;
        $this->addPointProcessor = $addPointProcessor;
        $this->mailService = $mailService;
        $this->orderStateMachine = $orderStateMachine;
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
        $util = new SDK($config);

        $charge = $util->getChargeBySubscriptionId($data->data->id);
        if (is_null($charge)) {
            return new Response('Charge not found', 404);
        }

        if ($this->Order->findOneBy(['univa_pay_charge_id' => $charge->id])) {
            return new Response('Order already created', 200);
        }

        $subscriptionOrder = $this->Order->findOneBy([
            'univa_pay_subscription_id' => $data->data->id,
            'OrderStatus' => UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE,
        ]);

        if (is_null($subscriptionOrder)) {
            return new Response(trans('univa_pay.error.webhook.subscription.not_found'), 404);
        }

        switch (WebhookEvent::fromValue($data->event)) {
            case WebhookEvent::SUBSCRIPTION_PAYMENT():
                $order = $this->createOrder($subscriptionOrder, $data, $charge, WebhookEvent::SUBSCRIPTION_PAYMENT());
                $charge->patch(['orderNo' => $order->getId()]);

                return new Response('successfully accepted', 201);
            case WebhookEvent::SUBSCRIPTION_FAILURE():
                $order = $this->createOrder($subscriptionOrder, $data, $charge, WebhookEvent::SUBSCRIPTION_FAILURE());
                $charge->patch(['orderNo' => $order->getId()]);

                return new Response('successfully accepted', 201);
            default:
        }

        return new Response();
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

    private function createOrder($existOrder, $data, $charge, $event)
    {
        // cloneで注文を複製してもidが変更できないため一から作成
        $newOrder = new Order();
        // 今回での決済の課金ID取得
        $newOrder->setUnivapayChargeId($charge->id);
        $newOrder->setUnivapaySubscriptionId($existOrder->getUnivapaySubscriptionId());
        $newOrder->setMessage($existOrder->getMessage());
        $newOrder->setName01($existOrder->getName01());
        $newOrder->setName02($existOrder->getName02());
        $newOrder->setKana01($existOrder->getKana01());
        $newOrder->setKana02($existOrder->getKana02());
        $newOrder->setCompanyName($existOrder->getCompanyName());
        $newOrder->setEmail($existOrder->getEmail());
        $newOrder->setPhoneNumber($existOrder->getPhoneNumber());
        $newOrder->setPostalCode($existOrder->getPostalCode());
        $newOrder->setAddr01($existOrder->getAddr01());
        $newOrder->setAddr02($existOrder->getAddr02());
        $newOrder->setBirth($existOrder->getBirth());
        // 今回決済金額から小計を逆算
        $newSubtotal = $existOrder->getSubtotal() - $existOrder->getTotal() + $data->data->amount - $existOrder->getDiscount();
        $newOrder->setSubtotal($newSubtotal);
        // 割引無効化
        $newOrder->setDiscount(0);
        $newOrder->setDeliveryFeeTotal($existOrder->getDeliveryFeeTotal());
        $newOrder->setCharge($existOrder->getCharge());
        $newOrder->setTax($existOrder->getTax());
        // 二回目以降の決済金額が違う場合があるため変更
        $newOrder->setTotal($data->data->amount);
        $newOrder->setPaymentTotal($data->data->amount);
        $newOrder->setPaymentMethod($existOrder->getPaymentMethod());
        $newOrder->setNote($existOrder->getNote());
        $newOrder->setCurrencyCode($existOrder->getCurrencyCode());
        $newOrder->setCompleteMessage($existOrder->getCompleteMessage());
        $newOrder->setCompleteMailMessage($existOrder->getCompleteMailMessage());
        // 決済日を今日に変更
        $newOrder->setPaymentDate(new \DateTime());
        $newOrder->setCustomer($existOrder->getCustomer());
        $newOrder->setCountry($existOrder->getCountry());
        $newOrder->setPref($existOrder->getPref());
        $newOrder->setSex($existOrder->getSex());
        $newOrder->setJob($existOrder->getJob());
        $newOrder->setPayment($existOrder->getPayment());
        $newOrder->setDeviceType($existOrder->getDeviceType());
        $newOrder->setCustomerOrderStatus($existOrder->getCustomerOrderStatus());
        $newOrder->setOrderStatusColor($existOrder->getOrderStatusColor());

        foreach ($existOrder->getOrderItems() as $value) {
            $newOrderItem = clone $value;
            // 値引きは引き継がない
            if ($newOrderItem->isDiscount() || $newOrderItem->isPoint()) {
                continue;
            }
            $newOrderItem->setOrder($newOrder);
            $newOrder->addOrderItem($newOrderItem);
        }
        foreach ($existOrder->getShippings() as $value) {
            $newShipping = clone $value;
            $newShipping->setShippingDeliveryDate(null);
            $newShipping->setShippingDeliveryTime(null);
            $newShipping->setTimeId(null);
            $newShipping->setShippingDate(null);
            $newShipping->setTrackingNumber(null);
            $newShipping->setOrder($newOrder);
            // 循環参照してしまい正常に発送データがセットできないため
            foreach ($newShipping->getOrderItems() as $v) {
                $newShipping->removeOrderItem($v);
            }
            foreach ($newOrder->getOrderItems() as $v) {
                if ($v->getShipping() && $v->getShipping()->getId() == $value->getId()) {
                    $v->setShipping($newShipping);
                    $newShipping->addOrderItem($v);
                }
            }
            $newOrder->addShipping($newShipping);
        }
        $purchaseContext = new PurchaseContext($newOrder, $newOrder->getCustomer());
        // ポイントを再計算4.0以前のバージョンの場合、先にポイントを再計算を行わないと正常に動かない
        $this->addPointProcessor->validate($newOrder, $purchaseContext);
        // 注文番号変更
        $preOrderId = $this->orderHelper->createPreOrderId();
        $newOrder->setPreOrderId($preOrderId);
        // 購入処理を完了
        $this->purchaseFlow->prepare($newOrder, $purchaseContext);
        $this->purchaseFlow->commit($newOrder, $purchaseContext);
        $this->entityManager->persist($newOrder);
        // 注文番号が重複しないように再採番
        $this->entityManager->flush();
        $this->orderNoProcessor->process($newOrder, $purchaseContext);
        $this->entityManager->flush();
        // 定期課金に失敗した場合はキャンセル済み注文に変更
        $OrderStatus = $this->orderStatusRepository->find($event === WebhookEvent::SUBSCRIPTION_PAYMENT() ? OrderStatus::PAID : OrderStatus::CANCEL);
        $newOrder->setOrderStatus($OrderStatus);
        if ($event === WebhookEvent::SUBSCRIPTION_PAYMENT()) {
            $this->mailService->sendOrderMail($newOrder);
        }
        $this->entityManager->flush();

        return $newOrder;
    }

    /**
     * subscription cancel action
     *
     * @Route("api/subscription/{id}", requirements={"id" = "\d+"}, name="univa_pay_cancel_subscription", methods={"PUT"})
     */
    public function cancelSubscription(Request $request, Order $order)
    {
        if ($request->isXmlHttpRequest() && $this->isTokenValid()) {
            try {
                $status = $this->orderStatusRepository->find(UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_CANCEL);
                $this->orderStateMachine->apply($order, $status);
                $this->entityManager->persist($order);
                $this->entityManager->flush();
            } catch (Exception $e) {
                UnivaPayLogger::get()->info('Failed to cancel subscription', [
                    'order_id' => $order->getId(),
                    'error' => $e->getMessage(),
                ]);

                return $this->json(['status' => 'error'], 500);
            }

            return $this->json(['status' => 'ok']);
        }

        throw new BadRequestHttpException();
    }

    /**
     * subscription get action
     *
     * @Route("/univapay/subscription/{id}", requirements={"id" = "\d+"}, name="univa_pay_get_subscription", methods={"GET"})
     */
    public function getSubscription(Request $request, Order $Order)
    {
        if ($request->isXmlHttpRequest() && $this->isTokenValid()) {
            $util = new SDK($this->Config->findOneById(1));
            $subscription = $util->getSubscriptionByChargeId($Order->getUnivapayChargeId());

            return $this->json(['status' => $subscription->status, 'id' => $subscription->id]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * subscription update action
     *
     * @Route("/univapay/subscription/update/{id}", requirements={"id" = "\d+"}, name="univa_pay_update_subscription", methods={"POST"})
     */
    public function updateSubscription(Request $request, Order $Order)
    {
        if ($request->isXmlHttpRequest() && $this->isTokenValid()) {
            $util = new SDK($this->Config->findOneById(1));
            $subscription = $util->getSubscriptionByChargeId($Order->getUnivapayChargeId());
            $subscription->patch($request->getContent());

            return $this->json($subscription->status);
        }

        throw new BadRequestHttpException();
    }
}
