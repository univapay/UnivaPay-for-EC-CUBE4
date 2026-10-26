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

namespace Plugin\UnivaPay;

use Eccube\Common\EccubeNav;

class Nav implements EccubeNav
{
    public static function getNav()
    {
        return [
            'order' => [
                'children' => [
                    'univa_pay_subscriptions' => [
                        'name' => 'univa_pay.admin.subscriptions.title',
                        'url' => 'univa_pay_admin_subscriptions',
                    ],
                ],
            ],
        ];
    }
}
