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

namespace Plugin\UnivaPay\Tests\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Order;
use Eccube\Repository\OrderRepository;
use Eccube\Service\MailService;
use PHPUnit\Framework\TestCase;
use Plugin\UnivaPay\Controller\Api\SubscriptionController;
use Plugin\UnivaPay\Entity\Config;
use Plugin\UnivaPay\Repository\ConfigRepository;
use Plugin\UnivaPay\Util\SDK;
use Symfony\Component\HttpFoundation\Request;

class SubscriptionWebhookIdempotencyTest extends TestCase
{
    public function testRepeatNotificationDoesNotSendMailOrUpdateChargeMetadata()
    {
        $existing = new Order();

        $orders = $this->createMock(OrderRepository::class);

        $orders->expects($this->once())
            ->method('findOneBy')
            ->with(['univa_pay_charge_id' => 'subscriptionAssociatedChargeId'])
            ->willReturn($existing);

        $mail = $this->createMock(MailService::class);
        $mail->expects($this->never())->method('sendOrderMail');

        $sdk = $this->createMock(SDK::class);

        $sdk->expects($this->once())
            ->method('getLatestChargeBySubscriptionId')
            ->with('subscriptionId')
            ->willReturn(new class {
                public function getId()
                {
                    return 'subscriptionAssociatedChargeId';
                }

                public function getSubscriptionId()
                {
                    return 'subscriptionId';
                }
            });

        $sdk->expects($this->never())
            ->method('updateChargeMetaData');

        $controller = $this->controller($orders, $mail);

        $controller->expects($this->once())
            ->method('createSdk')
            ->willReturn($sdk);

        $response = $controller->hook(
            $this->request('subscription_payment')
        );

        $this->assertSame(200, $response->getStatusCode());

        $this->assertSame(
            'Order already created',
            $response->getContent()
        );
    }

    public function testUnrelatedEventNeverLooksUpOrCreatesAnOrder()
    {
        $orders = $this->createMock(OrderRepository::class);

        $orders->expects($this->never())
            ->method('findOneBy');

        $mail = $this->createMock(MailService::class);

        $mail->expects($this->never())
            ->method('sendOrderMail');

        $controller = $this->controller($orders, $mail);

        $controller->expects($this->never())
            ->method('createSdk');

        $response = $controller->hook(
            $this->request('charge_updated')
        );

        $this->assertSame(200, $response->getStatusCode());

        $this->assertSame(
            'Ignored: unrelated event',
            $response->getContent()
        );
    }

    private function controller(
        OrderRepository $orders,
        MailService $mail,
    ): SubscriptionController {
        $configs = $this->createMock(ConfigRepository::class);

        $configs->method('get')
            ->with(1)
            ->willReturn(new Config());

        $controller = $this->getMockBuilder(
            SubscriptionController::class
        )
            ->setConstructorArgs([
                $configs,
                $orders,
                $mail,
            ])
            ->setMethods(['createSdk'])
            ->getMock();

        $controller->setEntityManager(
            $this->createMock(EntityManagerInterface::class)
        );

        return $controller;
    }

    private function request($event): Request
    {
        // Just mocking
        return Request::create(
            '/univapay/hook',
            'POST',
            [],
            [],
            [],
            [],
            json_encode([
                'event' => $event,
                'data' => [
                    'id' => 'subscriptionId',
                    'amount' => 100,
                ],
            ])
        );
    }
}
