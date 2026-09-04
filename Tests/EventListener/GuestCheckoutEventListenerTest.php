<?php

namespace Plugin\UnivaPay\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Plugin\UnivaPay\EventListener\GuestCheckoutEventListener;
use Plugin\UnivaPay\Util\SubscriptionCartChecker;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class GuestCheckoutEventListenerTest extends TestCase
{
    /** @var TokenStorageInterface|\PHPUnit_Framework_MockObject_MockObject */
    private $tokenStorage;

    /** @var AuthorizationCheckerInterface|\PHPUnit_Framework_MockObject_MockObject */
    private $authorizationChecker;

    /** @var RouterInterface|\PHPUnit_Framework_MockObject_MockObject */
    private $router;

    /** @var SubscriptionCartChecker|\PHPUnit_Framework_MockObject_MockObject */
    private $subscriptionCartChecker;

    public function setUp()
    {
        $this->setAuthenticated(false);
        $this->router = $this->createMock(RouterInterface::class);
        $this->subscriptionCartChecker = $this->createMock(SubscriptionCartChecker::class);
    }

    private function setAuthenticated($isAuthenticated)
    {
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->tokenStorage->method('getToken')->willReturn($this->createMock(TokenInterface::class));
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->authorizationChecker->method('isGranted')
            ->with('IS_AUTHENTICATED_REMEMBERED')->willReturn($isAuthenticated);
    }

    private function setNoToken()
    {
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->tokenStorage->method('getToken')->willReturn(null);
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->authorizationChecker->expects($this->never())->method('isGranted');
    }

    private function createListener()
    {
        return new GuestCheckoutEventListener(
            $this->tokenStorage,
            $this->authorizationChecker,
            $this->router,
            $this->subscriptionCartChecker
        );
    }

    private function makeEvent($route, $isXmlHttpRequest = false, $requestType = HttpKernelInterface::MASTER_REQUEST)
    {
        $server = $isXmlHttpRequest ? ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'] : [];
        $request = Request::create('/', 'GET', [], [], [], $server);
        $request->attributes->set('_route', $route);

        return new GetResponseEvent($this->createMock(HttpKernelInterface::class), $request, $requestType);
    }

    public function testBlocksGuestOnEveryGuestCheckoutRoute()
    {
        $this->subscriptionCartChecker->method('hasSubscriptionItem')->willReturn(true);
        $this->router->method('generate')->with('shopping_login')->willReturn('/shopping/login');

        foreach (GuestCheckoutEventListener::GUEST_CHECKOUT_ROUTES as $route) {
            $event = $this->makeEvent($route);
            $this->createListener()->onKernelRequest($event);

            $this->assertInstanceOf(RedirectResponse::class, $event->getResponse(), $route.' should be blocked');
            $this->assertSame('/shopping/login', $event->getResponse()->getTargetUrl(), $route);
        }
    }

    public function testReturnsJsonErrorForXhrRequest()
    {
        $this->subscriptionCartChecker->method('hasSubscriptionItem')->willReturn(true);
        $this->router->expects($this->never())->method('generate');

        $event = $this->makeEvent('shopping_customer', true);
        $this->createListener()->onKernelRequest($event);

        $this->assertInstanceOf(JsonResponse::class, $event->getResponse());
        $this->assertSame(403, $event->getResponse()->getStatusCode());
    }

    public function testAllowsGuestWithoutSubscriptionItem()
    {
        $this->subscriptionCartChecker->method('hasSubscriptionItem')->willReturn(false);

        $event = $this->makeEvent('shopping_nonmember');
        $this->createListener()->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testAllowsLoggedInCustomer()
    {
        $this->setAuthenticated(true);
        $this->subscriptionCartChecker->expects($this->never())->method('hasSubscriptionItem');

        $event = $this->makeEvent('shopping');
        $this->createListener()->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testTreatsMissingTokenAsGuest()
    {
        $this->setNoToken();
        $this->subscriptionCartChecker->method('hasSubscriptionItem')->willReturn(true);
        $this->router->method('generate')->willReturn('/shopping/login');

        $event = $this->makeEvent('shopping_nonmember');
        $this->createListener()->onKernelRequest($event);

        $this->assertInstanceOf(RedirectResponse::class, $event->getResponse());
    }
}
