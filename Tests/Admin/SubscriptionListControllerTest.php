<?php

namespace Plugin\UnivaPay\Tests\Admin;

use Eccube\Entity\Master\OrderStatus;
use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;
use Plugin\UnivaPay\Entity\SubscriptionPeriod;

class SubscriptionListControllerTest extends AbstractAdminWebTestCase
{
    public function testSearchWithSortingParameters(): void
    {
        foreach (['next', 'start', 'number'] as $sort) {
            foreach (['asc', 'desc'] as $direction) {
                $this->client->request(
                    'GET',
                    $this->generateUrl(
                        'univa_pay_admin_subscriptions',
                        [
                            'keyword' => '',
                            'status' => '',
                            'period' => '',
                            'date' => '',
                            'from' => '',
                            'to' => '',
                            'sort' => $sort,
                            'direction' => $direction,
                        ]
                    )
                );

                $this->assertSame(
                    200,
                    $this->client->getResponse()->getStatusCode(),
                    sprintf(
                        'Search failed: sort=%s, direction=%s',
                        $sort,
                        $direction
                    )
                );
            }
        }
    }

    public function testListWorksWithoutFilters(): void
    {
        $urls = [
            $this->generateUrl('univa_pay_admin_subscriptions'),

            $this->generateUrl('univa_pay_admin_subscriptions', [
                'keyword' => '',
                'status' => '',
                'period' => '',
                'date' => '',
                'from' => '',
                'to' => '',
                'sort' => 'next',
                'direction' => 'asc',
            ]),
        ];

        foreach ($urls as $url) {
            $crawler = $this->client->request('GET', $url);

            $this->assertSame(
                200,
                $this->client->getResponse()->getStatusCode()
            );

            $this->assertNotSame(
                false,
                strpos($crawler->html(), '定期課金一覧')
            );
        }
    }

    public function testListsParentsOnlyAndFiltersByCycleAndDate(): void
    {
        $parent = $this->createOrder($this->createCustomer());
        $parent->setOrderNo('LIST-PARENT-1402');
        $parent->setUnivapayParentSubscriptionId(
            'subscription-list-test'
        );
        $parent->setUnivapaySubscriptionPeriod(
            SubscriptionPeriod::MONTHLY
        );
        $parent->setUnivapayNextChargeDate(
            new \DateTime('2026-11-05')
        );
        $parent->setOrderStatus(
            $this->entityManager->find(
                OrderStatus::class,
                UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE
            )
        );

        $child = $this->createOrder($this->createCustomer());
        $child->setOrderNo('LIST-CHILD-1402');
        $child->setUnivapayParentSubscriptionId(
            'subscription-list-test'
        );
        $child->setUnivapayChargeId('charge-list-test');
        $child->setOrderStatus(
            $this->entityManager->find(
                OrderStatus::class,
                OrderStatus::PAID
            )
        );

        $this->entityManager->flush();

        $crawler = $this->client->request(
            'GET',
            $this->generateUrl(
                'univa_pay_admin_subscriptions',
                [
                    'period' => SubscriptionPeriod::MONTHLY,
                    'date' => '2026-11-05',
                    'keyword' => 'subscription-list-test',
                ]
            )
        );

        $html = $crawler->html();

        $this->assertNotSame(false, strpos($html, 'subscription-list-test'));
        $this->assertNotSame(false, strpos($html, $parent->getOrderNo()));
        $this->assertSame(false, strpos($html, 'LIST-CHILD-1402'));

        $crawler = $this->client->request(
            'GET',
            $this->generateUrl(
                'univa_pay_admin_subscriptions',
                [
                    'period' => SubscriptionPeriod::WEEKLY,
                    'keyword' => 'subscription-list-test',
                ]
            )
        );

        $this->assertSame(
            false,
            strpos($crawler->html(), $parent->getOrderNo())
        );
    }
}
