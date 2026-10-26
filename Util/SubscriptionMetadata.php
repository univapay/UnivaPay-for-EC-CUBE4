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

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Order;
use Plugin\UnivaPay\Entity\Config;
use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;
use Plugin\UnivaPay\Entity\SubscriptionPeriod;
use UnivaPay\Models\Subscription;
use UnivaPay\Models\SubscriptionStatus;

// Does 3 things :
//   1. Reads the billing cycle from the existing EC-CUBE order items
//   2. Maps UnivaPay subscription details to the parent order
//   3. Backfills existing parent orders during plugin update
//     a. Yes, the backfill is intentionally repeatable
//        because a parent whose billing cycle has already been populated is not fetched again
class SubscriptionMetadata
{
    private const PERIODS = [
        'daily' => SubscriptionPeriod::DAILY,
        'weekly' => SubscriptionPeriod::WEEKLY,
        'biweekly' => SubscriptionPeriod::BIWEEKLY,
        'monthly' => SubscriptionPeriod::MONTHLY,
        'bimonthly' => SubscriptionPeriod::BIMONTHLY,
        'quarterly' => SubscriptionPeriod::QUARTERLY,
        'semiannually' => SubscriptionPeriod::SEMIANNUALLY,
        'annually' => SubscriptionPeriod::ANNUALLY,
    ];

    public static function saveCheckoutPeriod(Order $order): void
    {
        foreach ($order->getProductOrderItems() as $item) {
            $productClass = $item->getProductClass();
            $period = $productClass
                ? $productClass->getSubscriptionPeriod()
                : null;

            if (
                $period
                && $period->getId() > SubscriptionPeriod::NON_SUBSCRIPTION
            ) {
                $order->setUnivapaySubscriptionPeriod($period->getId());

                return;
            }
        }
    }

    public static function update(
        Order $order,
        Subscription $subscription,
    ): void {
        $period = self::PERIODS[$subscription->getPeriod() ?? ''] ?? null;

        if ($period !== null) {
            $order->setUnivapaySubscriptionPeriod($period);
        }

        $nextPayment = $subscription->getNextPayment();
        $date = $nextPayment ? $nextPayment->getDueDate() : null;

        if (in_array($subscription->getStatus(), [
            SubscriptionStatus::CANCELED,
            SubscriptionStatus::COMPLETED,
            SubscriptionStatus::SUSPENDED,
        ], true)) {
            $date = null;
        }

        $order->setUnivapayNextChargeDate($date);
    }

    /**
     * DBAL is used so the backfill can execute during plugin updates
     * even when older OrderTrait metadata is still loaded.
     */
    public static function backfill(
        EntityManagerInterface $em,
        Config $config,
        ?callable $fetch = null,
    ): void {
        if (
            !$fetch
            && (!$config->getAppId() || !$config->getAppSecret())
        ) {
            UnivaPayLogger::get()->warning(
                'Subscription list backfill: API credentials not configured'
            );

            return;
        }

        $connection = $em->getConnection();

        $parents = $connection->fetchAll(
            'SELECT id, univa_pay_parent_subscription_id
             FROM dtb_order
             WHERE univa_pay_parent_subscription_id IS NOT NULL
               AND univa_pay_charge_id IS NULL
               AND order_status_id IN (?, ?, ?)
               AND univa_pay_subscription_period IS NULL',
            [
                UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE,
                UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_CANCEL,
                UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_SUSPEND,
            ]
        );

        $sdk = null;

        foreach ($parents as $parent) {
            try {
                if ($fetch) {
                    $subscription = $fetch(
                        $parent['univa_pay_parent_subscription_id']
                    );
                } else {
                    if ($sdk === null) {
                        $sdk = new SDK($config);
                    }

                    $subscription = $sdk->getSubscription(
                        $parent['univa_pay_parent_subscription_id']
                    );
                }

                if (!$subscription instanceof Subscription) {
                    throw new \UnexpectedValueException('Unexpected subscription API response');
                }

                $period = self::PERIODS[
                    $subscription->getPeriod() ?? ''
                ] ?? null;

                if ($period === null) {
                    throw new \UnexpectedValueException('Unknown subscription period');
                }

                $nextPayment = $subscription->getNextPayment();
                $date = $nextPayment
                    ? $nextPayment->getDueDate()
                    : null;

                if (in_array($subscription->getStatus(), [
                    SubscriptionStatus::CANCELED,
                    SubscriptionStatus::COMPLETED,
                    SubscriptionStatus::SUSPENDED,
                ], true)) {
                    $date = null;
                }

                $connection->update('dtb_order', [
                    'univa_pay_subscription_period' => $period,
                    'univa_pay_next_charge_date' => $date
                        ? $date->format('Y-m-d')
                        : null,
                ], [
                    'id' => $parent['id'],
                ]);
            } catch (\Throwable $e) {
                UnivaPayLogger::get()->warning(
                    'Subscription list backfill failed',
                    [
                        'subscription_id' => $parent['univa_pay_parent_subscription_id'],
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }
    }
}
