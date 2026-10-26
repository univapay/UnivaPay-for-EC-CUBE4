<?php



namespace Plugin\UnivaPay\EventListener;

use Eccube\Entity\MailHistory;
use Eccube\Exception\ShoppingException;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\MailHistoryRepository;
use Eccube\Repository\MailTemplateRepository;
use Eccube\Service\MailService;
use Plugin\UnivaPay\Repository\ConfigRepository;
use Plugin\UnivaPay\Util\Constants;
use Plugin\UnivaPay\Util\PaymentMethodChecker;
use Plugin\UnivaPay\Util\SDK;
use Plugin\UnivaPay\Util\SubscriptionMetadata;
use Plugin\UnivaPay\Util\UnivaPayApiException;
use Plugin\UnivaPay\Util\UnivaPayLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
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

    /** @var RequestStack */
    private $requestStack;

    /** @var MailerInterface */
    private $mailer;

    /** @var MailService */
    private $mailService;

    /** @var Environment */
    private $twig;

    public function __construct(
        BaseInfoRepository $baseInfoRepository,
        ConfigRepository $configRepository,
        MailHistoryRepository $mailHistoryRepository,
        MailTemplateRepository $mailTemplateRepository,
        RequestStack $requestStack,
        MailerInterface $mailer,
        MailService $mailService,
        Environment $twig,
    ) {
        $this->baseInfo = $baseInfoRepository->get();
        $this->configRepository = $configRepository;
        $this->mailHistoryRepository = $mailHistoryRepository;
        $this->mailTemplateRepository = $mailTemplateRepository;
        $this->requestStack = $requestStack;
        $this->mailer = $mailer;
        $this->mailService = $mailService;
        $this->twig = $twig;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.order.transition.subscription_suspend' => 'onSuspendSubscription',
            'workflow.order.transition.subscription_cancel' => 'onCancelSubscription',
            'workflow.order.transition.subscription_resume' => 'onResumeSubscription',
        ];
    }

    private function guard(callable $fn)
    {
        try {
            return $fn();
        } catch (UnivaPayApiException $e) {
            $message = trans('univa_pay.admin.order_edit.action_error', ['%message%' => $e->getMessage()]);
            $this->requestStack->getSession()->getFlashBag()->add('eccube.admin.error', $message);
            throw new ShoppingException($message);
        }
    }

    public function onSuspendSubscription(Event $event)
    {
        $order = $event->getSubject()->getOrder();
        if (!PaymentMethodChecker::isUnivaPay($order) || !$order->getUnivapayParentSubscriptionId()) {
            return;
        }

        $util = new SDK($this->configRepository->findOneById(1));

        $this->guard(function () use ($util, $order) {
            $subscription = $util->getSubscription($order->getUnivapayParentSubscriptionId());
            if ($subscription->getStatus() === SubscriptionStatus::SUSPENDED) {
                return;
            }

            $util->suspendSubscription($order->getUnivapayParentSubscriptionId());
            $order->setUnivapayNextChargeDate(null);
        });
    }

    public function onCancelSubscription(Event $event)
    {
        $order = $event->getSubject()->getOrder();
        if (!PaymentMethodChecker::isUnivaPay($order) || !$order->getUnivapayParentSubscriptionId()) {
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
        $order->setUnivapayNextChargeDate(null);
    }

    public function onResumeSubscription(Event $event)
    {
        $order = $event->getSubject()->getOrder();
        if (!PaymentMethodChecker::isUnivaPay($order) || !$order->getUnivapayParentSubscriptionId()) {
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
        try {
            SubscriptionMetadata::update(
                $order,
                $util->getSubscription(
                    $order->getUnivapayParentSubscriptionId()
                )
            );
        } catch (\Throwable $e) {
            UnivaPayLogger::get()->warning(
                'Subscription metadata refresh after resume failed',
                [
                    'subscription_id' => $order->getUnivapayParentSubscriptionId(),
                    'error' => $e->getMessage(),
                ]
            );
        }
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

        $message = (new Email())
            ->subject('['.$this->baseInfo->getShopName().'] '.$MailTemplate->getMailSubject())
            ->from(new Address($this->baseInfo->getEmail01(), $this->baseInfo->getShopName()))
            ->to($this->mailService->convertRFCViolatingEmail($order->getEmail()))
            ->bcc($this->baseInfo->getEmail01())
            ->replyTo($this->baseInfo->getEmail03())
            ->returnPath($this->baseInfo->getEmail04())
            ->text($body);

        try {
            $this->mailer->send($message);
        } catch (TransportExceptionInterface $e) {
            UnivaPayLogger::get()->critical($e->getMessage());
        }

        $MailHistory = new MailHistory();
        $MailHistory->setMailSubject($message->getSubject())
            ->setMailBody($message->getTextBody())
            ->setOrder($order)
            ->setSendDate(new \DateTime());
        $this->mailHistoryRepository->save($MailHistory);

        UnivaPayLogger::get()->info('サブスク停止メール送信完了');
    }
}
