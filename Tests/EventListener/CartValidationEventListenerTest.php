<?php

namespace Plugin\UnivaPay\Tests\EventListener;

use Eccube\Entity\Cart;
use Eccube\Entity\CartItem;
use Eccube\Entity\Customer;
use Eccube\Event\EventArgs;
use Eccube\Service\CartService;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use PHPUnit\Framework\TestCase;
use Plugin\UnivaPay\Entity\SubscriptionPeriod;
use Plugin\UnivaPay\EventListener\CartValidationEventListener;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class CartValidationEventListenerTest extends TestCase
{
    /** @var CartService */
    private $cartService;

    /** @var Session|\PHPUnit_Framework_MockObject_MockObject */
    private $session;

    /** @var FlashBagInterface|\PHPUnit_Framework_MockObject_MockObject */
    private $flashBag;

    /** @var RouterInterface|\PHPUnit_Framework_MockObject_MockObject */
    private $router;

    /** @var PurchaseFlow|\PHPUnit_Framework_MockObject_MockObject */
    private $purchaseFlow;

    /** @var TokenStorageInterface|\PHPUnit_Framework_MockObject_MockObject */
    private $tokenStorage;

    public function setUp()
    {
        $this->cartService = $this->createMock(CartService::class);
        $this->session = $this->createMock(Session::class);
        $this->flashBag = $this->createMock(FlashBagInterface::class);
        $this->session->method('getFlashBag')->willReturn($this->flashBag);
        $this->router = $this->createMock(RouterInterface::class);
        $this->purchaseFlow = $this->createMock(PurchaseFlow::class);
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
    }

    private function createListener()
    {
        return new CartValidationEventListener(
            $this->cartService,
            $this->session,
            $this->router,
            $this->purchaseFlow,
            $this->tokenStorage
        );
    }

    private function makeCartItem($subscriptionPeriodId)
    {
        $subscriptionPeriod = $subscriptionPeriodId !== null
            ? (new SubscriptionPeriod())->setId($subscriptionPeriodId)
            : null;

        $productClass = new class($subscriptionPeriod) {
            private $subscriptionPeriod;

            public function __construct($subscriptionPeriod)
            {
                $this->subscriptionPeriod = $subscriptionPeriod;
            }

            public function getSubscriptionPeriod()
            {
                return $this->subscriptionPeriod;
            }
        };

        $cartItem = $this->createMock(CartItem::class);
        $cartItem->method('getProductClass')->willReturn($productClass);

        return $cartItem;
    }

    private function makeCart(array $cartItems)
    {
        $cart = new Cart();
        foreach ($cartItems as $cartItem) {
            $cart->addCartItem($cartItem);
        }

        return $cart;
    }

    private function makeEventArgs($productClassId, $isXmlHttpRequest)
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('getData')->willReturn(['product_class_id' => $productClassId, 'quantity' => 1]);

        $server = $isXmlHttpRequest ? ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'] : [];
        $request = Request::create('/products/add_cart', 'POST', [], [], [], $server);

        return new EventArgs(['form' => $form, 'Product' => null], $request);
    }

    public function testNoActionWhenCartHasSingleNonSubscriptionItem()
    {
        $cart = $this->makeCart([$this->makeCartItem(null)]);
        $this->cartService->method('getCart')->willReturn($cart);
        $this->cartService->expects($this->never())->method('removeProduct');
        $this->cartService->expects($this->never())->method('save');
        $this->purchaseFlow->expects($this->never())->method('validate');
        $this->flashBag->expects($this->never())->method('add');

        $event = $this->makeEventArgs(1, false);
        $this->createListener()->onCartComplete($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testNoActionWhenCartHasSingleSubscriptionItem()
    {
        $cart = $this->makeCart([$this->makeCartItem(SubscriptionPeriod::MONTHLY)]);
        $this->cartService->method('getCart')->willReturn($cart);
        $this->cartService->expects($this->never())->method('removeProduct');
        $this->flashBag->expects($this->never())->method('add');

        $event = $this->makeEventArgs(1, false);
        $this->createListener()->onCartComplete($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testNoActionWhenMultipleItemsButNoneSubscription()
    {
        $cart = $this->makeCart([$this->makeCartItem(null), $this->makeCartItem(null)]);
        $this->cartService->method('getCart')->willReturn($cart);
        $this->cartService->expects($this->never())->method('removeProduct');
        $this->flashBag->expects($this->never())->method('add');

        $event = $this->makeEventArgs(2, false);
        $this->createListener()->onCartComplete($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testRemovesItemAndRedirectsForNonXhrRequest()
    {
        $cart = $this->makeCart([$this->makeCartItem(SubscriptionPeriod::MONTHLY), $this->makeCartItem(null)]);
        $this->cartService->method('getCart')->willReturn($cart);
        $this->cartService->method('getCarts')->willReturn([$cart]);

        $this->cartService->expects($this->once())->method('removeProduct')->with(42);
        $this->purchaseFlow->expects($this->once())->method('validate')
            ->with($cart, $this->isInstanceOf(PurchaseContext::class));
        $this->cartService->expects($this->once())->method('save');
        $this->flashBag->expects($this->once())->method('add')
            ->with('eccube.front.request.error', $this->anything());
        $this->router->expects($this->once())->method('generate')->with('cart')->willReturn('/cart');

        $event = $this->makeEventArgs(42, false);
        $this->createListener()->onCartComplete($event);

        $this->assertTrue($event->hasResponse());
        $this->assertInstanceOf(RedirectResponse::class, $event->getResponse());
        $this->assertSame('/cart', $event->getResponse()->getTargetUrl());
    }

    public function testRemovesItemWithoutRedirectForXhrRequest()
    {
        $cart = $this->makeCart([$this->makeCartItem(SubscriptionPeriod::MONTHLY), $this->makeCartItem(null)]);
        $this->cartService->method('getCart')->willReturn($cart);
        $this->cartService->method('getCarts')->willReturn([$cart]);

        $this->cartService->expects($this->once())->method('removeProduct')->with(42);
        $this->cartService->expects($this->once())->method('save');
        $this->flashBag->expects($this->once())->method('add')
            ->with('eccube.front.request.error', $this->anything());
        $this->router->expects($this->never())->method('generate');

        $event = $this->makeEventArgs(42, true);
        $this->createListener()->onCartComplete($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testPurchaseContextCarriesAuthenticatedUser()
    {
        $customer = new Customer();
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($customer);
        $this->tokenStorage->method('getToken')->willReturn($token);

        $cart = $this->makeCart([$this->makeCartItem(SubscriptionPeriod::MONTHLY), $this->makeCartItem(null)]);
        $this->cartService->method('getCart')->willReturn($cart);
        $this->cartService->method('getCarts')->willReturn([$cart]);

        $this->purchaseFlow->expects($this->once())->method('validate')
            ->with($cart, $this->callback(function (PurchaseContext $context) use ($customer) {
                return $context->getUser() === $customer;
            }));

        $this->createListener()->onCartComplete($this->makeEventArgs(42, true));
    }

    /**
     * @dataProvider anonymousTokenProvider
     */
    public function testPurchaseContextHasNoUserForAnonymousOrMissingToken($token)
    {
        $this->tokenStorage->method('getToken')->willReturn($token);

        $cart = $this->makeCart([$this->makeCartItem(SubscriptionPeriod::MONTHLY), $this->makeCartItem(null)]);
        $this->cartService->method('getCart')->willReturn($cart);
        $this->cartService->method('getCarts')->willReturn([$cart]);

        $this->purchaseFlow->expects($this->once())->method('validate')
            ->with($cart, $this->callback(function (PurchaseContext $context) {
                return $context->getUser() === null;
            }));

        $this->createListener()->onCartComplete($this->makeEventArgs(42, true));
    }

    public function anonymousTokenProvider()
    {
        $anonymousToken = $this->createMock(TokenInterface::class);
        $anonymousToken->method('getUser')->willReturn('anon.');

        return [
            'no token' => [null],
            'anonymous token' => [$anonymousToken],
        ];
    }

    public function testNonSubscriptionMasterRowIsNotTreatedAsSubscription()
    {
        // The product explicitly has the "非定期" (id=0) master row set, not null.
        $cart = $this->makeCart([
            $this->makeCartItem(SubscriptionPeriod::NON_SUBSCRIPTION),
            $this->makeCartItem(null),
        ]);
        $this->cartService->method('getCart')->willReturn($cart);
        $this->cartService->expects($this->never())->method('removeProduct');
        $this->cartService->expects($this->never())->method('save');
        $this->purchaseFlow->expects($this->never())->method('validate');
        $this->flashBag->expects($this->never())->method('add');

        $event = $this->makeEventArgs(2, false);
        $this->createListener()->onCartComplete($event);

        $this->assertFalse($event->hasResponse());
    }
}
