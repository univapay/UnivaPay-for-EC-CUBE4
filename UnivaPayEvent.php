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

namespace Plugin\UnivaPay;

use Eccube\Event\TemplateEvent;
use Plugin\UnivaPay\Repository\ConfigRepository;
use Plugin\UnivaPay\Util\PaymentMethodChecker;
use Plugin\UnivaPay\Util\SDK;
use Plugin\UnivaPay\Util\SubscriptionCartChecker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UnivaPayEvent implements EventSubscriberInterface
{
    private $subscriptionCartChecker;

    private $configRepository;

    public function __construct(SubscriptionCartChecker $subscriptionCartChecker, ConfigRepository $configRepository)
    {
        $this->subscriptionCartChecker = $subscriptionCartChecker;
        $this->configRepository = $configRepository;
    }

    /**
     * リッスンしたいサブスクライバのイベント名の配列を返します。
     * 配列のキーはイベント名、値は以下のどれかをしてします。
     * - 呼び出すメソッド名
     * - 呼び出すメソッド名と優先度の配列
     * - 呼び出すメソッド名と優先度の配列の配列
     * 優先度を省略した場合は0
     *
     * 例：
     * - array('eventName' => 'methodName')
     * - array('eventName' => array('methodName', $priority))
     * - array('eventName' => array(array('methodName1', $priority), array('methodName2')))
     *
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            '@admin/Order/edit.twig' => 'onAdminOrderEditTwig',
            'Mypage/history.twig' => 'onMypageHistoryTwig',
            'Mypage/withdraw.twig' => 'onMyPageWithdrawTwig',
            'Shopping/confirm.twig' => 'onShoppingConfirmTwig',
            'Shopping/login.twig' => 'onShoppingLoginTwig',
        ];
    }

    public function onAdminOrderEditTwig(TemplateEvent $event)
    {
        $this->setIsUnivaPayOrder($event);
        $event->addSnippet('@UnivaPay/admin/order_edit.twig');
    }

    public function onMypageHistoryTwig(TemplateEvent $event)
    {
        $Order = $event->hasParameter('Order') ? $event->getParameter('Order') : null;
        if (PaymentMethodChecker::isUnivaPay($Order) && $Order->getUnivapayParentSubscriptionId()) {
            $sdk = new SDK($this->configRepository->findOneById(1));
            $event->setParameter('UnivaPayCustomerPageUrl', $sdk->getCustomerPageUrl($Order->getUnivapayParentSubscriptionId(), $Order->getEmail()));
        }
        $event->addSnippet('@UnivaPay/mypage_history.twig');
    }

    public function onMyPageWithdrawTwig(TemplateEvent $event)
    {
        $event->addSnippet('@UnivaPay/mypage_withdraw.twig');
    }

    public function onShoppingConfirmTwig(TemplateEvent $event)
    {
        $this->setIsUnivaPayOrder($event);
        $event->addSnippet('@UnivaPay/shopping_confirm.twig');
    }

    public function onShoppingLoginTwig(TemplateEvent $event)
    {
        $event->setParameter('UnivaPaySubscriptionInCart', $this->subscriptionCartChecker->hasSubscriptionItem());
        $event->addSnippet('@UnivaPay/shopping_login.twig');
    }

    private function setIsUnivaPayOrder(TemplateEvent $event)
    {
        $Order = $event->hasParameter('Order') ? $event->getParameter('Order') : null;
        $event->setParameter('IsUnivaPayOrder', PaymentMethodChecker::isUnivaPay($Order));
    }
}
