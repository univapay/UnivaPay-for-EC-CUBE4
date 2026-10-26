<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
