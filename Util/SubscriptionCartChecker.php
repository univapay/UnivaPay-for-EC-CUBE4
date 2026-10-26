<?php

namespace Plugin\UnivaPay\Util;

use Eccube\Service\CartService;
use Plugin\UnivaPay\Entity\SubscriptionPeriod;

class SubscriptionCartChecker
{
    private $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    public function hasSubscriptionItem()
    {
        $Cart = $this->cartService->getCart();

        if ($Cart === null) {
            return false;
        }

        foreach ($Cart->getCartItems() as $item) {
            $ProductClass = $item->getProductClass();
            if ($ProductClass === null) {
                continue;
            }

            $SubscriptionPeriod = $ProductClass->getSubscriptionPeriod();
            if ($SubscriptionPeriod !== null && $SubscriptionPeriod->getId() !== SubscriptionPeriod::NON_SUBSCRIPTION) {
                return true;
            }
        }

        return false;
    }
}
