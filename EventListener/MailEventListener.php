<?php

namespace Plugin\UnivaPay\EventListener;

use Eccube\Common\Constant;
use Eccube\Entity\Order;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Repository\MailTemplateRepository;
use Plugin\UnivaPay\Resource\Constants;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MailEventListener implements EventSubscriberInterface
{
    private $mailTemplateRepository;
    private $twig;

    public function __construct(
        MailTemplateRepository $mailTemplateRepository,
        \Twig_Environment $twig
    ) {
        $this->mailTemplateRepository = $mailTemplateRepository;
        $this->twig = $twig;
    }

    public static function getSubscribedEvents()
    {
        return [
            EccubeEvents::MAIL_ORDER => 'onMailOrder',
        ];
    }

    // Refer to Eccube MailService class
    public function onMailOrder(EventArgs $event)
    {
        /** @var Order $order */
        $order = $event->getArgument('Order');

        if ($order->getUnivapaySubscriptionId() && !$order->getUnivapayChargeId()) {
            $msg = $event->getArgument('message');

            $subscriptionMailTemplate = $this->mailTemplateRepository->findOneBy([
                'name' => Constants::MAIL_TEMPLATE_UNIVAPAY_SUBSCRIPTION_NAME
            ]);

            $msg->setSubject($subscriptionMailTemplate->getMailSubject());
            $body = $this->twig->render($subscriptionMailTemplate->getFileName(), [
                'Order' => $order,
            ]);

            $htmlFileName = $this->getHtmlTemplate($subscriptionMailTemplate->getFileName());
            if (!is_null($htmlFileName)) {
                $htmlBody = $this->twig->render($htmlFileName, [
                    'Order' => $order,
                ]);

                $msg
                    ->setContentType('text/plain; charset=UTF-8')
                    ->setBody($body, 'text/plain')
                    ->addPart($htmlBody, 'text/html');
            } else {
                $msg->setBody($body);
            }
        }
    }
 
    public function getHtmlTemplate($templateName)
    {
        $fileName = explode('.', $templateName);
        $suffix = '.html';
        $htmlFileName = $fileName[0].$suffix.'.'.$fileName[1];

        if ($this->twig->getLoader()->exists($htmlFileName)) {
            return $htmlFileName;
        } else {
            return null;
        }
    }
}
