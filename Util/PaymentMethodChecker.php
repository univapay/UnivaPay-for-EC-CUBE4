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

use Eccube\Entity\Order;
use Plugin\UnivaPay\Service\Method\CreditCard;

class PaymentMethodChecker
{
    /**
     * @param Order|null $Order
     */
    public static function isUnivaPay($Order): bool
    {
        if (!$Order instanceof Order) {
            return false;
        }

        $Payment = $Order->getPayment();
        if (!$Payment) {
            return false;
        }

        return $Payment->getMethodClass() === CreditCard::class;
    }
}
