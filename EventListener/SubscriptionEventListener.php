<?php

namespace Plugin\UnivaPay\EventListener;

use Eccube\Entity\MailHistory;
use Eccube\Entity\Order;
use Eccube\Exception\ShoppingException;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\MailHistoryRepository;
use Eccube\Repository\MailTemplateRepository;
use Plugin\UnivaPay\Repository\ConfigRepository;
use Plugin\UnivaPay\Util\Constants;
use Plugin\UnivaPay\Util\PaymentMethodChecker;
use Plugin\UnivaPay\Util\SDK;
use Plugin\UnivaPay\Util\UnivaPayApiException;
use Plugin\UnivaPay\Util\UnivaPayLogger;
use Swift_Mailer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Workflow\Event\Event;
use Twig\Environment;
use UnivaPay\Models\SubscriptionStatus;

class SubscriptionEventListener implements EventSubscriberInterface
{
    /** @var BaseInfoRepository */
    private $baseInfo;

    /** @var ConfigRepository */
    private $configRepository;

    /** @var MailHistoryRepository */
    private $mailHistoryRepository;

    /** @var MailTemplateRepository */
    private $mailTemplateRepository;

    /** @var SessionInterface */
    private $session;

    /** @var Swift_Mailer */
    private $mailer;

    /** @var Environment */
    private $twig;

    public function __construct(
        BaseInfoRepository $baseInfoRepository,
        ConfigRepository $configRepository,
        MailHistoryRepository $mailHistoryRepository,
        MailTemplateRepository $mailTemplateRepository,
        SessionInterface $session,
        Swift_Mailer $mailer,
        Environment $twig
    ) {
        $this->baseInfo = $baseInfoRepository->get();
        $this->configRepository = $configRepository;
        $this->mailHistoryRepository = $mailHistoryRepository;
        $this->mailTemplateRepository = $mailTemplateRepository;
        $this->session = $session;
        $this->mailer = $mailer;
        $this->twig = $twig;
    }

    public static function getSubscribedEvents()
    {
        return [
            'workflow.order.transition.subscription_suspend' => 'onSuspendSubscription',
            'workflow.order.transition.subscription_cancel' => 'onCancelSubscription',
            'workflow.order.transition.subscription_resume' => 'onResumeSubscription',
        ];
    }

    private function isUnivapayPayment(Order $order): bool
    {
        return PaymentMethodChecker::isUnivaPay($order);
    }

    private function guard(callable $fn)
    {
        try {
            return $fn();
        } catch (UnivaPayApiException $e) {
            $message = trans('univa_pay.admin.order_edit.action_error', ['%message%' => $e->getMessage()]);
            $this->session->getFlashBag()->add('eccube.admin.error', $message);
            throw new ShoppingException($message);
        }
    }

    public function onSuspendSubscription(Event $event)
    {
        $order = $event->getSubject()->getOrder();
        if (!$this->isUnivapayPayment($order)) {
            return;
        }

        $util = new SDK($this->configRepository->findOneById(1));

        $this->guard(function () use ($util, $order) {
            $subscription = $util->getSubscription($order->getUnivapayParentSubscriptionId());
            if ($subscription->getStatus() === SubscriptionStatus::SUSPENDED) {
                return;
            }

            $util->suspendSubscription($order->getUnivapayParentSubscriptionId());
        });
    }

    public function onCancelSubscription(Event $event)
    {
        $order = $event->getSubject()->getOrder();
        if (!$this->isUnivapayPayment($order)) {
            return;
        }

        UnivaPayLogger::get()->info('サブスク停止処理開始', ['order' => $order->getId()]);

        $util = new SDK($this->configRepository->findOneById(1));

        $this->guard(function () use ($util, $order) {
            $subscription = $util->getSubscription($order->getUnivapayParentSubscriptionId());
            if ($subscription->getStatus() !== SubscriptionStatus::CANCELED) {
                $util->cancelSubscription($order->getUnivapayParentSubscriptionId());
                $subscription = $util->getSubscription($order->getUnivapayParentSubscriptionId());
            }

            if ($subscription->getStatus() === SubscriptionStatus::CANCELED) {
                $this->sendEmailCancelSubscription($order);
            }
        });
    }

    public function onResumeSubscription(Event $event)
    {
        $order = $event->getSubject()->getOrder();
        if (!$this->isUnivapayPayment($order)) {
            return;
        }

        $util = new SDK($this->configRepository->findOneById(1));

        $this->guard(function () use ($util, $order) {
            $subscription = $util->getSubscription($order->getUnivapayParentSubscriptionId());
            if ($subscription->getStatus() === SubscriptionStatus::CURRENT) {
                return;
            }

            $util->unsuspendSubscription($order->getUnivapayParentSubscriptionId());
        });
    }

    private function sendEmailCancelSubscription($order)
    {
        UnivaPayLogger::get()->info('サブスク停止メール送信開始');

        $MailTemplate = $this->mailTemplateRepository->findOneBy([
            'name' => Constants::MAIL_TEMPLATE_UNIVAPAY_SUBSCRIPTION_CANCEL,
        ]);

        $body = $this->twig->render($MailTemplate->getFileName(), [
            'BaseInfo' => $this->baseInfo,
            'Customer' => $order->getCustomer(),
            'Order' => $order,
        ]);

        $message = (new \Swift_Message())
            ->setSubject('['.$this->baseInfo->getShopName().'] '.$MailTemplate->getMailSubject())
            ->setFrom([$this->baseInfo->getEmail01() => $this->baseInfo->getShopName()])
            ->setTo([$order->getEmail()])
            ->setBcc($this->baseInfo->getEmail01())
            ->setReplyTo($this->baseInfo->getEmail03())
            ->setReturnPath($this->baseInfo->getEmail04());

        $message->setBody($body);

        $count = $this->mailer->send($message);

        $MailHistory = new MailHistory();
        $MailHistory->setMailSubject($message->getSubject())
            ->setMailBody($message->getBody())
            ->setOrder($order)
            ->setSendDate(new \DateTime());

        $multipart = $message->getChildren();
        if (count($multipart) > 0) {
            $MailHistory->setMailHtmlBody($multipart[0]->getBody());
        }
        $this->mailHistoryRepository->save($MailHistory);

        UnivaPayLogger::get()->info('サブスク停止メール送信完了', ['count' => $count]);
    }
}
