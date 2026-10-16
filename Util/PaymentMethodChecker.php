<?php

namespace Plugin\UnivaPay\Util;

use Eccube\Entity\Order;
use Plugin\UnivaPay\Service\Method\CreditCard;

class PaymentMethodChecker
{
    /**
     * Determines whether the order is being paid for using UnivaPay.
     *
     * @param Order|null $Order
     */
    public static function isUnivaPay($Order): bool
    {
        if (!$Order instanceof Order) {
            return false;
        }

        $Payment = $Order->getPayment();
        if ($Payment && $Payment->getMethodClass()) {
            return $Payment->getMethodClass() === CreditCard::class;
        }

        return $Order->getPaymentMethod() === Constants::UNIVAPAY_PAYMENT_METHOD;
    }
}
