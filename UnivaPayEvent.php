<?php
namespace Plugin\UnivaPay;

use Eccube\Event\TemplateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UnivaPayEvent implements EventSubscriberInterface
{
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
    public static function getSubscribedEvents()
    {
        return [
            '@admin/Order/edit.twig' => 'onAdminOrderEditTwig',
            'Mypage/history.twig' => 'onMypageHistoryTwig',
            'Mypage/withdraw.twig' => 'onMyPageWithdrawTwig',
            'Shopping/confirm.twig' => 'onShoppingConfirmTwig'
        ];
    }

    public function onAdminOrderEditTwig(TemplateEvent $event)
    {
        $event->addSnippet('@UnivaPay/admin/order_edit.twig');
    }

    public function onMypageHistoryTwig(TemplateEvent $event)
    {
        $event->addSnippet('@UnivaPay/mypage_history.twig');
    }

    public function onMyPageWithdrawTwig(TemplateEvent $event)
    {
        $event->addSnippet('@UnivaPay/mypage_withdraw.twig');
    }

    public function onShoppingConfirmTwig(TemplateEvent $event)
    {
        $event->addSnippet('@UnivaPay/shopping_confirm.twig');
    }
}
