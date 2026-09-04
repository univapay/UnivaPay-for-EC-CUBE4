<?php

namespace Plugin\UnivaPay\EventListener;

use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Service\CartService;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Plugin\UnivaPay\Entity\SubscriptionPeriod;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CartValidationEventListener implements EventSubscriberInterface
{
    private $cartService;
    private $session;
    private $router;
    private $cartPurchaseFlow;
    private $tokenStorage;

    public function __construct(
        CartService $cartService,
        SessionInterface $session,
        RouterInterface $router,
        PurchaseFlow $cartPurchaseFlow,
        TokenStorageInterface $tokenStorage
    ) {
        $this->cartService = $cartService;
        $this->session = $session;
        $this->router = $router;
        $this->cartPurchaseFlow = $cartPurchaseFlow;
        $this->tokenStorage = $tokenStorage;
    }

    public static function getSubscribedEvents()
    {
        return [
            EccubeEvents::FRONT_PRODUCT_CART_ADD_COMPLETE => 'onCartComplete',
        ];
    }

    // only allow one subscription item in the cart
    public function onCartComplete(EventArgs $event)
    {
        $hasSubscription = false;

        foreach ($this->cartService->getCart()->getCartItems() as $item) {
            $subscriptionPeriod = $item->getProductClass()->getSubscriptionPeriod();
            if ($subscriptionPeriod !== null && $subscriptionPeriod->getId() !== SubscriptionPeriod::NON_SUBSCRIPTION) {
                $hasSubscription = true;
                break;
            }
        }

        if ($hasSubscription && count($this->cartService->getCart()->getCartItems()) > 1) {
            // the item was already persisted by CartService::save() before this event
            $addCartData = $event->getArgument('form')->getData();
            $this->cartService->removeProduct($addCartData['product_class_id']);
            // recalculate
            foreach ($this->cartService->getCarts() as $Cart) {
                $this->cartPurchaseFlow->validate($Cart, new PurchaseContext($Cart, $this->getCurrentUser()));
            }
            $this->cartService->save();

            $message = trans('univa_pay.error.cart.subscription.only_one_item');
            $request = $event->getRequest();

            if ($request->isXmlHttpRequest()) {
                $this->session->getFlashBag()->add('eccube.front.request.error', $message);
            } else {
                $this->session->getFlashBag()->add('eccube.front.request.error', $message);
                $event->setResponse(new RedirectResponse($this->router->generate('cart')));
            }
        }
    }

    private function getCurrentUser()
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return null;
        }
        
        $user = $token->getUser();
        if (!is_object($user)) {
            return null;
        }

        return $user;
    }
}
