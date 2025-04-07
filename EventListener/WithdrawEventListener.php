<?php

namespace Plugin\UnivaPay\EventListener;

use Exception;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Repository\OrderRepository;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class WithdrawEventListener implements EventSubscriberInterface
{
    private $orderRepository;
    private $tokenStorage;
    private $session;

    public function __construct(
        OrderRepository $orderRepository,
        TokenStorageInterface $tokenStorage,
        SessionInterface $session
    ) {
        $this->orderRepository = $orderRepository;
        $this->tokenStorage = $tokenStorage;
        $this->session = $session;
    }

    public static function getSubscribedEvents()
    {
        return [
            EccubeEvents::FRONT_MYPAGE_WITHDRAW_INDEX_INITIALIZE => 'onCustomerWithdrawInitialize',
            EccubeEvents::FRONT_MYPAGE_WITHDRAW_INDEX_COMPLETE => 'onCustomerWithdraw'
        ];
    }

    private function getCustomer()
    {
        $token = $this->tokenStorage->getToken();
        if ($token && $token->getUser() instanceof \Eccube\Entity\Customer) {
            return $token->getUser();
        }

        return null;
    }

    public function onCustomerWithdrawInitialize(EventArgs $event)
    {
        $customer = $this->getCustomer();

        $activeSubscriptions = $this->orderRepository->findBy([
            'Customer' => $customer,
            'OrderStatus' => UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE,
        ]);

        if (count($activeSubscriptions) > 0) {
            $this->session->getFlashBag()->add('eccube.front.warning', trans('univa_pay.error.customer.withdraw.has_subscription'));
        }
    }

    public function onCustomerWithdraw(EventArgs $event)
    {
        $customer = $event->getArgument('Customer');

        $activeSubscriptions = $this->orderRepository->findBy([
            'Customer' => $customer,
            'OrderStatus' => UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE,
        ]);

        if (count($activeSubscriptions) > 0) {
            log_info('Customer: '.$customer->getId().' has active subscriptions');
            $this->session->getFlashBag()->add('eccube.front.error', trans('univa_pay.error.customer.withdraw.has_subscription'));
            throw new Exception(trans('univa_pay.error.customer.withdraw.has_subscription'));
        }
    }
}
