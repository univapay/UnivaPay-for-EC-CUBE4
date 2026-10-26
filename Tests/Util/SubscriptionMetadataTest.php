<?php

namespace Plugin\UnivaPay\Tests\Util;

use Eccube\Entity\Order;
use PHPUnit\Framework\TestCase;
use Plugin\UnivaPay\Entity\SubscriptionPeriod;
use Plugin\UnivaPay\Entity\OrderTrait;
use Plugin\UnivaPay\Util\SubscriptionMetadata;
use UnivaPay\Models\Subscription;
use UnivaPay\Models\SubscriptionNextPayment;
use UnivaPay\Models\SubscriptionStatus;

require_once __DIR__ . '/../../Resource/vendor/autoload.php';
class SubscriptionMetadataTest extends TestCase
{

    private function newSubscriptionOrder(): Order
    {
        return new class() extends Order {
            use OrderTrait;
        };
    }
    public function testUpdatesPeriodAndDueDateFromSdk(): void
    {
        $order = $this->newSubscriptionOrder();

        $subscription = new Subscription();
        $subscription->setPeriod('weekly');
        $subscription->setStatus(SubscriptionStatus::CURRENT);

        $payment = new SubscriptionNextPayment();
        $payment->setDueDate(new \DateTime('2026-11-02'));
        $subscription->setNextPayment($payment);

        SubscriptionMetadata::update($order, $subscription);

        $this->assertSame(
            SubscriptionPeriod::WEEKLY,
            $order->getUnivapaySubscriptionPeriod()
        );
        $this->assertSame(
            '2026-11-02',
            $order->getUnivapayNextChargeDate()->format('Y-m-d')
        );
    }

    /** @dataProvider stoppedStatuses */
    public function testStoppedSubscriptionsHaveNoUpcomingCharge(
        string $status
    ): void {
        $order = $this->newSubscriptionOrder();

        $subscription = new Subscription();
        $subscription->setPeriod('monthly');
        $subscription->setStatus($status);

        $payment = new SubscriptionNextPayment();
        $payment->setDueDate(new \DateTime('2026-11-02'));
        $subscription->setNextPayment($payment);

        SubscriptionMetadata::update($order, $subscription);

        $this->assertSame(
            SubscriptionPeriod::MONTHLY,
            $order->getUnivapaySubscriptionPeriod()
        );
        $this->assertNull($order->getUnivapayNextChargeDate());
    }

    public static function stoppedStatuses(): array
    {
        return [
            [SubscriptionStatus::CANCELED],
            [SubscriptionStatus::SUSPENDED],
            [SubscriptionStatus::COMPLETED],
        ];
    }
}
