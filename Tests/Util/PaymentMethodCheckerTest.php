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
    private function makeOrder($methodName, $methodClass, $hasPayment = true)
    {
        $Order = new Order();
        $Order->setPaymentMethod($methodName);

        if ($hasPayment) {
            $Payment = new Payment();
            $Payment->setMethod($methodName);
            $Payment->setMethodClass($methodClass);
            $Order->setPayment($Payment);
        }

        return $Order;
    }

    public function testNullOrder()
    {
        $this->assertFalse(PaymentMethodChecker::isUnivaPay(null));
    }

    public function testUnivaPayOrder()
    {
        $Order = $this->makeOrder(Constants::UNIVAPAY_PAYMENT_METHOD, CreditCard::class);

        $this->assertTrue(PaymentMethodChecker::isUnivaPay($Order));
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

    public function testOtherPaymentMethod()
    {
        $Order = $this->makeOrder('銀行振込', 'Eccube\Service\Payment\Method\Cash');

        $this->assertFalse(PaymentMethodChecker::isUnivaPay($Order));
    }

    public function testFallsBackToMethodNameWhenPaymentIsMissing()
    {
        $univaPayOrder = $this->makeOrder(Constants::UNIVAPAY_PAYMENT_METHOD, null, false);
        $otherOrder = $this->makeOrder('銀行振込', null, false);

        $this->assertTrue(PaymentMethodChecker::isUnivaPay($univaPayOrder));
        $this->assertFalse(PaymentMethodChecker::isUnivaPay($otherOrder));
    }

    public function testFallsBackWhenMethodClassIsNull()
    {
        $Order = $this->makeOrder(Constants::UNIVAPAY_PAYMENT_METHOD, null);

        $this->assertTrue(PaymentMethodChecker::isUnivaPay($Order));
    }
}
