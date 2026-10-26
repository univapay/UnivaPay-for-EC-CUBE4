<?php



namespace Plugin\UnivaPay\Tests\Web;

use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Payment;
use Eccube\Service\Payment\Method\Cash;
use Eccube\Tests\Web\AbstractWebTestCase;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;

class SubscriptionCancelTest extends AbstractWebTestCase
{
    private function createSubscriptionParent($Customer)
    {
        $Order = $this->createOrder($Customer);
        // Non-UnivaPay payment keeps the cancel transition from calling the API; the fixture picks payments at random.
        $Order->setPayment($this->entityManager->getRepository(Payment::class)->findOneBy(['method_class' => Cash::class]));
        $Order->setOrderStatus($this->entityManager->find(OrderStatus::class, UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE));
        $Order->setUnivapayParentSubscriptionId($this->getFaker()->uuid());
        $this->entityManager->flush();

        return $Order;
    }

    private function cancel($Order)
    {
        $this->client->request('PUT', $this->generateUrl('univa_pay_cancel_subscription', ['id' => $Order->getId()]), ['_token' => 'dummy']);
    }

    public function testOwnerCanCancel()
    {
        $Customer = $this->createCustomer();
        $Order = $this->createSubscriptionParent($Customer);
        $this->loginTo($Customer);

        $this->cancel($Order);

        $this->assertTrue($this->client->getResponse()->isRedirect($this->generateUrl('mypage_history', ['order_no' => $Order->getOrderNo()])));
        $this->entityManager->refresh($Order);
        $this->assertSame(UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_CANCEL, $Order->getOrderStatus()->getId());
    }

    public function testOtherCustomerCannotCancel()
    {
        $Order = $this->createSubscriptionParent($this->createCustomer());
        $this->loginTo($this->createCustomer());

        $this->cancel($Order);

        $this->assertSame(404, $this->client->getResponse()->getStatusCode());
        $this->entityManager->refresh($Order);
        $this->assertSame(UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE, $Order->getOrderStatus()->getId());
    }
}
