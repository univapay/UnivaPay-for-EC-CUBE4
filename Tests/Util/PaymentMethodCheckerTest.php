<?php



namespace Plugin\UnivaPay\Tests\Util;

use Eccube\Entity\Order;
use Eccube\Entity\Payment;
use PHPUnit\Framework\TestCase;
use Plugin\UnivaPay\Service\Method\CreditCard;
use Plugin\UnivaPay\Util\Constants;
use Plugin\UnivaPay\Util\PaymentMethodChecker;

class PaymentMethodCheckerTest extends TestCase
{
    private function makeOrder($methodName, $methodClass)
    {
        $Payment = new Payment();
        $Payment->setMethod($methodName);
        $Payment->setMethodClass($methodClass);
        $Order = new Order();
        $Order->setPaymentMethod($methodName);
        $Order->setPayment($Payment);

        return $Order;
    }

    public function testNullOrder()
    {
        $this->assertFalse(PaymentMethodChecker::isUnivaPay(null));
    }

    public function testRenamedPaymentMethodIsStillDetected()
    {
        $Order = $this->makeOrder('クレジットカード', CreditCard::class);

        $this->assertTrue(PaymentMethodChecker::isUnivaPay($Order));
    }

    public function testOtherPluginNamedUnivaPayIsNotDetected()
    {
        $Order = $this->makeOrder(Constants::UNIVAPAY_PAYMENT_METHOD, 'Plugin\OtherPayment\Service\Method\Card');

        $this->assertFalse(PaymentMethodChecker::isUnivaPay($Order));
    }
}
