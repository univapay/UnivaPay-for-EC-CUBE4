<?php

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
