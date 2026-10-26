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

use Eccube\DependencyInjection\Facade\LoggerFacade;

class UnivaPayLogger
{
    public static function get()
    {
        try {
            return logs('univapay');
        } catch (\Throwable $e) {
            // The univapay channel is missing while an update from an older plugin version still runs its old config.
            return LoggerFacade::create();
        }
    }
}
