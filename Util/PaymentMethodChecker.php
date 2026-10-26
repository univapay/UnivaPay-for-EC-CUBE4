<?php



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
