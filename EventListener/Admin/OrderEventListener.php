<?php

namespace Plugin\UnivaPay\EventListener\Admin;

use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Exception;
use Plugin\UnivaPay\Repository\ConfigRepository;
use Plugin\UnivaPay\Util\Constants;
use Plugin\UnivaPay\Util\SDK;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Univapay\Enums\SubscriptionStatus;

class OrderEventListener implements EventSubscriberInterface
{
    private $configRepository;
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
        if (!$order || $order->getPaymentMethod() !== Constants::UNIVAPAY_PAYMENT_METHOD) {
            return;
        }

        $subscriptionId = $order->getUnivapaySubscriptionId();
        if ($subscriptionId) {
            $order = $this->handleSubscription($order, $subscriptionId);
        }

        $chargeId = $order->getUnivapayChargeId();
        if ($chargeId) {
            $order = $this->handleCharge($order, $chargeId);
        }

        $event->setArgument('TargetOrder', $order);
    }

    private function handleSubscription($order, $subscriptionId): object
    {
        try {
            $util = new SDK($this->configRepository->findAll()[0]);
            $subscription = $util->getSubscription($subscriptionId);
            $order->univapaySubscription = $subscription;
            switch($subscription->status)
            {
                case SubscriptionStatus::UNVERIFIED():
                    $order->univapaySubscriptionStatus = trans('univa_pay.admin.subscription.status.unverified');
                    break;
                case SubscriptionStatus::UNCONFIRMED():
                    $order->univapaySubscriptionStatus = trans('univa_pay.admin.subscription.status.unconfirmed');
                    break;
                case SubscriptionStatus::UNPAID():
                    $order->univapaySubscriptionStatus = trans('univa_pay.admin.subscription.status.unpaid');
                    break;
                case SubscriptionStatus::AUTHORIZED():
                    $order->univapaySubscriptionStatus = trans('univa_pay.admin.subscription.status.unauthorized');
                    break;
                case SubscriptionStatus::CURRENT():
                    $order->univapaySubscriptionStatus = trans('univa_pay.admin.subscription.status.current');
                    break;
                case SubscriptionStatus::SUSPENDED():
                    $order->univapaySubscriptionStatus = trans('univa_pay.admin.subscription.status.suspended');
                    break;
                case SubscriptionStatus::CANCELED():
                    $order->univapaySubscriptionStatus = trans('univa_pay.admin.subscription.status.canceled');
                    break;
                case SubscriptionStatus::COMPLETED():
                    $order->univapaySubscriptionStatus = trans('univa_pay.admin.subscription.status.completed');
                    break;
                default:
                    $order->univapaySubscriptionStatus = '';
            }
        } catch (Exception $e) {
            log_error($e->getMessage());
            $order->univapaySubscriptionStatus = null;
            $this->session->getFlashBag()->set('eccube.admin.error', trans('univa_pay.error.request').$e->getMessage());
        }

        return $order;
    }

    private function handleCharge($order, $chargeId): object
    {
        try {
            $util = new SDK($this->configRepository->findAll()[0]);
            $charge = $util->getCharge($chargeId);
            $order->univapayCharge = $charge;
            $order->univapayRefund = $charge->listRefunds();
        } catch (Exception $e) {
            log_error($e->getMessage());
            $order->univapayCharge = null;
            $order->univapayRefund = null;
            $this->session->getFlashBag()->set('eccube.admin.error', trans('univa_pay.error.request').$e->getMessage());
        }
        return $order;
    }
}
