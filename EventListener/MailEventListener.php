<?php



namespace Plugin\UnivaPay\EventListener;

use Eccube\Entity\Order;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\MailTemplateRepository;
use Plugin\UnivaPay\Util\Constants;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twig\Environment;

class MailEventListener implements EventSubscriberInterface
{
    private $baseInfo;
    private $mailTemplateRepository;
    private $twig;

    public function __construct(
        BaseInfoRepository $baseInfoRepository,
        MailTemplateRepository $mailTemplateRepository,
        Environment $twig,
    ) {
        $this->baseInfo = $baseInfoRepository->get();
        $this->mailTemplateRepository = $mailTemplateRepository;
        $this->twig = $twig;
    }

    public static function getSubscribedEvents(): array
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

        if ($order->isUnivapaySubscriptionParent()) {
            $msg = $event->getArgument('message');

            $subscriptionMailTemplate = $this->mailTemplateRepository->findOneBy([
                'name' => Constants::MAIL_TEMPLATE_UNIVAPAY_SUBSCRIPTION_ACTIVE,
            ]);

            $msg->subject('['.$this->baseInfo->getShopName().'] '.$subscriptionMailTemplate->getMailSubject());
            $msg->text($this->twig->render($subscriptionMailTemplate->getFileName(), [
                'Order' => $order,
            ]));

            // Null clears the HTML part core rendered from the regular order template.
            $htmlFileName = $this->getHtmlTemplate($subscriptionMailTemplate->getFileName());
            $msg->html(is_null($htmlFileName) ? null : $this->twig->render($htmlFileName, [
                'Order' => $order,
            ]));
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
