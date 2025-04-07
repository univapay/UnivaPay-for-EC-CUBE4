<?php

namespace Plugin\UnivaPay\Tests\Admin;

use Eccube\Entity\Master\OrderStatus;
use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;
use Plugin\UnivaPay\Util\Constants;

/**
 * Test Admin Order Related Pages 
 */
class OrderControllerTest extends AbstractAdminWebTestCase
{
    private $mockOrders; 

    public function setUp()
    {
        parent::setUp();

        $faker = $this->getFaker();
        $this->mockOrders = [
            $this->createMockOrder($faker->uuid(), null),
            $this->createMockOrder(null, $faker->uuid()),
        ];
    }

    private function createMockOrder(?string $chargeId, ?string $subscriptionId)
    {
        $order = $this->createOrder($this->createCustomer());
        $order->setPaymentMethod(Constants::UNIVAPAY_PAYMENT_METHOD);
        $orderStatus = $this->entityManager->getRepository(OrderStatus::class)->find(OrderStatus::PAID);
        $order->setOrderStatus($orderStatus);
        $order->setUnivapayChargeId($chargeId);
        $order->setUnivapaySubscriptionId($subscriptionId);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    // Should be able to search by UnivaPay Charge / Subscription ID
    public function testSearchOrdersByUnivapayIds()
    {
        $crawler = $this->client->request('GET', $this->generateUrl('admin_order'));

        // Search by UnivaPay Charge ID
        $form = $crawler->selectButton('検索')->form();
        $form['admin_search_order[multi]'] = $this->mockOrders[0]->getUnivapayChargeId();
        $crawler = $this->client->submit($form);
        $this->assertContains($this->mockOrders[0]->getUnivapayChargeId(), $crawler->html());
        $this->assertNotContains($this->mockOrders[1]->getUnivapaySubscriptionId(), $crawler->html());

        // Search by UnivaPay Subscription ID
        $form = $crawler->selectButton('検索')->form();
        $form['admin_search_order[multi]'] = $this->mockOrders[1]->getUnivapaySubscriptionId();
        $crawler = $this->client->submit($form);
        $this->assertContains($this->mockOrders[1]->getUnivapaySubscriptionId(), $crawler->html());
        $this->assertNotContains($this->mockOrders[0]->getUnivapayChargeId(), $crawler->html());
    }
}
