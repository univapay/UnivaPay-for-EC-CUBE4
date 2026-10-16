<?php

namespace Plugin\UnivaPay\EventListener\Admin;

use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Plugin\UnivaPay\Repository\ConfigRepository;
use Plugin\UnivaPay\Util\PaymentMethodChecker;
use Plugin\UnivaPay\Util\SDK;
use Plugin\UnivaPay\Util\UnivaPayApiException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use UnivaPay\Models\SubscriptionStatus;

class OrderEventListener implements EventSubscriberInterface
{
    /** @var ConfigRepository */
    private $configRepository;

    /** @var SessionInterface */
    private $session;

    public function __construct(
        ConfigRepository $configRepository,
        SessionInterface $session
    ) {
        $this->configRepository = $configRepository;
        $this->session = $session;
    }

    public static function getSubscribedEvents()
    {
        return [
            EccubeEvents::ADMIN_ORDER_EDIT_INDEX_INITIALIZE => 'onOrderEditIndex',
            EccubeEvents::ADMIN_ORDER_EDIT_INDEX_COMPLETE => 'onOrderEditIndex',
        ];
    }

    public function onOrderEditIndex(EventArgs $event)
    {
        $order = $event->getArgument('TargetOrder');
        if (!PaymentMethodChecker::isUnivaPay($order)) {
            return;
        }

        if ($order->isUnivapaySubscriptionParent()) {
            $order = $this->fetchSubscription($order, $order->getUnivapayParentSubscriptionId());
        }

        $chargeId = $order->getUnivapayChargeId();
        if ($chargeId) {
            $order = $this->fetchCharge($order, $chargeId);
        }

        $event->setArgument('TargetOrder', $order);
    }

    private function guard(callable $fn)
    {
        try {
            return $fn();
        } catch (UnivaPayApiException $e) {
            $this->session->getFlashBag()->add('eccube.admin.error', trans('univa_pay.admin.order_edit.fetch_error', ['%message%' => $e->getMessage()]));

            return null;
        }
    }

    private function fetchSubscription($order, string $subscriptionId): object
    {
        $util = new SDK($this->configRepository->findAll()[0]);

        $subscription = $this->guard(function () use ($util, $subscriptionId) {
            return $util->getSubscription($subscriptionId);
        });

        if ($subscription === null) {
            return $order;
        }

        $order->univapaySubscription = $subscription;
        switch ($subscription->getStatus()) {
            case SubscriptionStatus::UNVERIFIED:
                $order->univapaySubscriptionStatus = trans('univa_pay.admin.subscription.status.unverified');
                break;
            case SubscriptionStatus::UNCONFIRMED:
                $order->univapaySubscriptionStatus = trans('univa_pay.admin.subscription.status.unconfirmed');
                break;
            case SubscriptionStatus::UNPAID:
                $order->univapaySubscriptionStatus = trans('univa_pay.admin.subscription.status.unpaid');
                break;
            case SubscriptionStatus::CURRENT:
                $order->univapaySubscriptionStatus = trans('univa_pay.admin.subscription.status.current');
                break;
            case SubscriptionStatus::SUSPENDED:
                $order->univapaySubscriptionStatus = trans('univa_pay.admin.subscription.status.suspended');
                break;
            case SubscriptionStatus::CANCELED:
                $order->univapaySubscriptionStatus = trans('univa_pay.admin.subscription.status.canceled');
                break;
            case SubscriptionStatus::COMPLETED:
                $order->univapaySubscriptionStatus = trans('univa_pay.admin.subscription.status.completed');
                break;
            default:
                $order->univapaySubscriptionStatus = null;
        }

        return $order;
    }

    private function fetchCharge($order, string $chargeId): object
    {
        $util = new SDK($this->configRepository->findAll()[0]);

        $this->guard(function () use ($util, $order, $chargeId) {
            $order->univapayCharge = $util->getCharge($chargeId);
            $order->univapayRefund = $util->getRefunds($chargeId);
        });

        return $order;
    }
}
