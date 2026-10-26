<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\UnivaPay\EventListener;

use Eccube\Exception\ShoppingException;
use Plugin\UnivaPay\Repository\ConfigRepository;
use Plugin\UnivaPay\Util\PaymentMethodChecker;
use Plugin\UnivaPay\Util\SDK;
use Plugin\UnivaPay\Util\UnivaPayApiException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Workflow\Event\Event;
use UnivaPay\Models\ChargeStatus;

class OrderEventListener implements EventSubscriberInterface
{
    /** @var ConfigRepository */
    private $configRepository;

    /** @var RequestStack */
    private $requestStack;

    public function __construct(
        ConfigRepository $configRepository,
        RequestStack $requestStack,
    ) {
        $this->configRepository = $configRepository;
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.order.transition.pay' => 'onPayOrder',
            'workflow.order.transition.cancel' => 'onCancelOrder',
        ];
    }

    private function guard(callable $fn)
    {
        try {
            return $fn();
        } catch (UnivaPayApiException $e) {
            $message = trans('univa_pay.admin.order_edit.action_error', ['%message%' => $e->getMessage()]);
            $this->requestStack->getSession()->getFlashBag()->add('eccube.admin.error', $message);
            throw new ShoppingException($message);
        }
    }

    public function onPayOrder(Event $event)
    {
        $order = $event->getSubject()->getOrder();
        if (!PaymentMethodChecker::isUnivaPay($order) || !$order->getUnivapayChargeId()) {
            return;
        }

        $util = new SDK($this->configRepository->findOneById(1));

        $this->guard(function () use ($util, $order) {
            $charge = $util->getCharge($order->getUnivapayChargeId());

            switch ($charge->getStatus()) {
                case ChargeStatus::AUTHORIZED:
                    $util->captureCharge($order, $charge);
                    break;
                default:
                    break;
            }
        });
    }

    public function onCancelOrder(Event $event)
    {
        $order = $event->getSubject()->getOrder();
        if (!PaymentMethodChecker::isUnivaPay($order) || !$order->getUnivapayChargeId()) {
            return;
        }

        $util = new SDK($this->configRepository->findOneById(1));

        $this->guard(function () use ($util, $order) {
            $charge = $util->getCharge($order->getUnivapayChargeId());

            switch ($charge->getStatus()) {
                case ChargeStatus::AUTHORIZED:
                    $util->createChargeCancel($order, $charge);
                    break;
                case ChargeStatus::SUCCESSFUL:
                    $refund = $util->getRefunds($charge->getId());
                    if ($refund->getTotalHits() > 0) {
                        break;
                    }

                    $util->createChargeRefund($charge);
                    break;
                default:
                    break;
            }
        });
    }
}
