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

use Plugin\UnivaPay\Entity\Master\UnivaPayOrderStatus;

$container->loadFromExtension('framework', [
    'workflows' => [
        'order' => [
            'places' => [
                (string) UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE,
                (string) UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_SUSPEND,
                (string) UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_CANCEL,
            ],
            'transitions' => [
                'subscription_suspend' => [
                    'from' => [
                        (string) UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE,
                    ],
                    'to' => (string) UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_SUSPEND,
                ],
                'subscription_cancel' => [
                    'from' => [
                        (string) UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE,
                        (string) UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_SUSPEND,
                    ],
                    'to' => (string) UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_CANCEL,
                ],
                'subscription_resume' => [
                    'from' => [
                        (string) UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_SUSPEND,
                    ],
                    'to' => (string) UnivaPayOrderStatus::UNIVAPAY_SUBSCRIPTION_ACTIVE,
                ],
            ],
        ],
    ],
]);
