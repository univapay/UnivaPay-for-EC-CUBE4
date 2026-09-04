<?php

namespace Plugin\UnivaPay\Tests\Util;

use Eccube\Entity\Cart;
use Eccube\Entity\CartItem;
use Eccube\Service\CartService;
use PHPUnit\Framework\TestCase;
use Plugin\UnivaPay\Entity\SubscriptionPeriod;
use Plugin\UnivaPay\Util\SubscriptionCartChecker;

class SubscriptionCartCheckerTest extends TestCase
{
    /** @var CartService|\PHPUnit_Framework_MockObject_MockObject */
    private $cartService;

    public function setUp()
    {
        $this->cartService = $this->createMock(CartService::class);
    }

    private function makeCartItem($subscriptionPeriodId, $hasProductClass = true)
    {
        $cartItem = $this->createMock(CartItem::class);

        if (!$hasProductClass) {
            $cartItem->method('getProductClass')->willReturn(null);

            return $cartItem;
        }

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

    public function testReturnsFalseWhenCartIsNull()
    {
        $this->cartService->method('getCart')->willReturn(null);

        $this->assertFalse((new SubscriptionCartChecker($this->cartService))->hasSubscriptionItem());
    }

    public function testReturnsFalseForNonSubscriptionCart()
    {
        $cart = $this->makeCart([
            $this->makeCartItem(null),
            $this->makeCartItem(SubscriptionPeriod::NON_SUBSCRIPTION),
            $this->makeCartItem(null, false),
        ]);
        $this->cartService->method('getCart')->willReturn($cart);

        $this->assertFalse((new SubscriptionCartChecker($this->cartService))->hasSubscriptionItem());
    }

    public function testReturnsTrueWhenSubscriptionItemExists()
    {
        $cart = $this->makeCart([
            $this->makeCartItem(null),
            $this->makeCartItem(SubscriptionPeriod::MONTHLY),
        ]);
        $this->cartService->method('getCart')->willReturn($cart);

        $this->assertTrue((new SubscriptionCartChecker($this->cartService))->hasSubscriptionItem());
    }
}
