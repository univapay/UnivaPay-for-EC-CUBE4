<?php



namespace Plugin\UnivaPay\EventListener;

use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Repository\OrderRepository;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;
use Plugin\UnivaPay\Util\UnivaPayLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class WithdrawEventListener implements EventSubscriberInterface
{
    private $orderRepository;
    private $tokenStorage;
    private $requestStack;

    public function __construct(
        OrderRepository $orderRepository,
        TokenStorageInterface $tokenStorage,
        RequestStack $requestStack,
    ) {
        $this->orderRepository = $orderRepository;
        $this->tokenStorage = $tokenStorage;
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EccubeEvents::FRONT_MYPAGE_WITHDRAW_INDEX_INITIALIZE => 'onCustomerWithdrawInitialize',
            EccubeEvents::FRONT_MYPAGE_WITHDRAW_INDEX_COMPLETE => 'onCustomerWithdraw',
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
            $this->requestStack->getSession()->getFlashBag()->add('eccube.front.warning', trans('univa_pay.error.customer.withdraw.has_subscription'));
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
            UnivaPayLogger::get()->info('Customer: '.$customer->getId().' has active subscriptions');
            $this->requestStack->getSession()->getFlashBag()->add('eccube.front.error', trans('univa_pay.error.customer.withdraw.has_subscription'));
            throw new \Exception(trans('univa_pay.error.customer.withdraw.has_subscription'));
        }
    }
}
