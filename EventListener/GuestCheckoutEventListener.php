<?php

namespace Plugin\UnivaPay\EventListener;

use Plugin\UnivaPay\Util\SubscriptionCartChecker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class GuestCheckoutEventListener implements EventSubscriberInterface
{
    const GUEST_CHECKOUT_ROUTES = [
        'shopping',
        'shopping_nonmember',
        'shopping_customer',
        'shopping_redirect_to',
        'shopping_confirm',
        'shopping_checkout',
        'shopping_shipping',
        'shopping_shipping_edit',
        'shopping_shipping_multiple',
        'shopping_shipping_multiple_edit',
    ];

    private $tokenStorage;
    private $authorizationChecker;
    private $router;
    private $subscriptionCartChecker;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        AuthorizationCheckerInterface $authorizationChecker,
        RouterInterface $router,
        SubscriptionCartChecker $subscriptionCartChecker
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->authorizationChecker = $authorizationChecker;
        $this->router = $router;
        $this->subscriptionCartChecker = $subscriptionCartChecker;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!in_array($request->attributes->get('_route'), self::GUEST_CHECKOUT_ROUTES, true)) {
            return;
        }

        if ($this->isMember() || !$this->subscriptionCartChecker->hasSubscriptionItem()) {
            return;
        }

        log_info('[UnivaPay] 定期購入商品がカートにあるため非会員購入をブロックしました.', [$request->attributes->get('_route')]);

        if ($request->isXmlHttpRequest()) {
            $event->setResponse(new JsonResponse(['status' => 'NG'], 403));

            return;
        }

        $event->setResponse(new RedirectResponse($this->router->generate('shopping_login')));
    }

    private function isMember()
    {
        return $this->tokenStorage->getToken()
            && $this->authorizationChecker->isGranted('IS_AUTHENTICATED_REMEMBERED');
    }
}
