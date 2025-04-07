<?php

namespace Plugin\UnivaPay\EventListener;

use Exception;
use Eccube\Entity\MailHistory;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\MailHistoryRepository;
use Eccube\Repository\MailTemplateRepository;
use Plugin\UnivaPay\Util\SDK;
use Plugin\UnivaPay\Util\Constants;
use Plugin\UnivaPay\Repository\ConfigRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Workflow\Event\Event;
use Univapay\Enums\SubscriptionStatus;

class SubscriptionEventListener implements EventSubscriberInterface
{
    private $baseInfo;
    private $configRepository;
    private $mailer;
    private $mailHistoryRepository;
    private $mailTemplateRepository;
    private $session;
    private $twig;

    public function __construct(
        BaseInfoRepository $baseInfoRepository,
        ConfigRepository $configRepository,
        MailHistoryRepository $mailHistoryRepository,
        MailTemplateRepository $mailTemplateRepository,
        SessionInterface $session,
        \Swift_Mailer $mailer,
        \Twig\Environment $twig
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

    public function onSuspendSubscription(Event $event)
    {
        $order = $event->getSubject()->getOrder();

        if ($order->getPaymentMethod() !== 'UnivaPay') {
            return;
        }

        try {
            $util = new SDK($this->configRepository->findOneById(1));
            $subscription = $util->getSubscription($order->getUnivapaySubscriptionId());
            $subscription->patch(
                null,
                null,
                null,
                null,
                SubscriptionStatus::SUSPENDED()
            );
            $subscription->awaitResult(5);
        } catch (Exception $e) {
            $this->handleError($e->getMessage());
        }
    }

    public function onCancelSubscription(Event $event)
    {
        $order = $event->getSubject()->getOrder();

        if ($order->getPaymentMethod() !== 'UnivaPay') {
            return;
        }

        try {
            log_info('サブスク停止処理開始', ['order' => $order->getId()]);

            $util = new SDK($this->configRepository->findOneById(1));
            $subscription = $util->getSubscription($order->getUnivapaySubscriptionId());
            $subscription->cancel();
            $subscription = $subscription->awaitResult(5);

            if ($subscription->status === SubscriptionStatus::CANCELED()) {
                $this->sendEmailCancelSubscription($order);
            }
        } catch (Exception $e) {
            $this->handleError($e->getMessage());
        }
    }

    public function onResumeSubscription(Event $event)
    {
        $order = $event->getSubject()->getOrder();

        if ($order->getPaymentMethod() !== 'UnivaPay') {
            return;
        }

        try {
            $util = new SDK($this->configRepository->findOneById(1));
            $subscription = $util->getSubscription($order->getUnivapaySubscriptionId());
            $subscription->patch(
                null,
                null,
                null,
                null,
                SubscriptionStatus::UNPAID()
            );
            $subscription->awaitResult(5);
        } catch (Exception $e) {
            $this->handleError($e->getMessage());
        }
    }

    private function sendEmailCancelSubscription($order)
    {
        log_info('サブスク停止メール送信開始');

        $MailTemplate = $this->mailTemplateRepository->findOneBy([
            'name' => Constants::MAIL_TEMPLATE_UNIVAPAY_SUBSCRIPTION_CANCEL
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

        log_info('サブスク停止メール送信完了', ['count' => $count]);
    }

    private function handleError($message)
    {
        log_error($message);
        if ($this->session->has('_security_admin')) {
            $this->session->getFlashBag()->add('eccube.admin.error', $message);
        } else {
            $this->session->getFlashBag()->add('eccube.front.error', $message);
        }
    }
}
