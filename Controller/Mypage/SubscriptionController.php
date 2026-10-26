<?php



namespace Plugin\UnivaPay\Controller\Mypage;

use Eccube\Controller\AbstractController;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\OrderStateMachine;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;
use Plugin\UnivaPay\Util\UnivaPayLogger;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class SubscriptionController extends AbstractController
{
    /** @var OrderStatusRepository */
    private $orderStatusRepository;

    /** @var OrderStateMachine */
    private $orderStateMachine;

    public function __construct(
        OrderStatusRepository $orderStatusRepository,
        OrderStateMachine $orderStateMachine,
    ) {
        $this->orderStatusRepository = $orderStatusRepository;
        $this->orderStateMachine = $orderStateMachine;
    }

    #[Route(
        path: '/mypage/univapay/subscription/{id}/cancel',
        requirements: ['id' => '\\d+'],
        name: 'univa_pay_cancel_subscription',
        methods: ['PUT']
    )]
    public function cancel(Order $order)
    {
        $this->isTokenValid();

        if (!$order->getCustomer() || $order->getCustomer()->getId() !== $this->getUser()->getId()) {
            throw new NotFoundHttpException();
        }

        $status = $this->orderStatusRepository->find(UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_CANCEL);
        if ($this->orderStateMachine->can($order, $status)) {
            try {
                $this->orderStateMachine->apply($order, $status);
                $this->entityManager->flush();
            } catch (\Exception $e) {
                UnivaPayLogger::get()->info('Failed to cancel subscription', [
                    'order_id' => $order->getId(),
                    'error' => $e->getMessage(),
                ]);
                $this->addError('univa_pay.mypage.subscription.cancel.error');
            }
        }

        return $this->redirectToRoute('mypage_history', ['order_no' => $order->getOrderNo()]);
    }
}
