<?php

namespace Plugin\UnivaPay\EventListener;

use Exception;
use Money\Currency;
use Money\Money;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Plugin\UnivaPay\Util\SDK;
use Plugin\UnivaPay\Repository\ConfigRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Univapay\Enums\ChargeStatus;

// Listener to update charge status on Univapay
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
            'workflow.order.transition.pay' => 'onPayOrder',
            'workflow.order.transition.cancel' => 'onCancelOrder',
        ];
    }

    public function onPayOrder(Event $event)
    {
        $order = $event->getSubject()->getOrder();

        if ($order->getPaymentMethod() !== 'UnivaPay') {
            return;
        }

        try {
            $util = new SDK($this->configRepository->findOneById(1));
            $charge = $util->getCharge($order->getUnivapayChargeId());
            $charge->capture();
            $charge->awaitResult(5);
        } catch (Exception $e) {
            $this->handleError($e->getMessage());
        }
    }

    public function onCancelOrder(Event $event)
    {
        $order = $event->getSubject()->getOrder();

        if ($order->getPaymentMethod() !== 'UnivaPay') {
            return;
        }

        try {
            $util = new SDK($this->configRepository->findOneById(1));
            $charge = $util->getCharge($order->getUnivapayChargeId());
            if($charge->status === ChargeStatus::SUCCESSFUL()) {
                // Capture -> Refund
                $money = new Money($charge->chargedAmountFormatted, new Currency($charge->chargedCurrency.""));
                $charge->createRefund($money);
                $charge->awaitResult(5);
            } else {
                // Authorized -> Cancel
                $charge->cancel();
                $charge->awaitResult(5);
            }
        } catch (Exception $e) {
            $this->handleError($e->getMessage());
        }
    }

    private function handleError($message)
    {
        log_error($message);
        if ($this->session->has('_security_admin')) {
            $this->session->getFlashBag()->add('eccube.admin.error', $message);
        } else {
            $this->session->getFlashBag()->add('eccube.front.error', $message);
        }
    }
}
