<?php

namespace Plugin\UnivaPay\Util;

use Eccube\Application;
use Eccube\DependencyInjection\Facade\LoggerFacade;

class UnivaPayLogger
{
    public static function get()
    {
        try {
            return logs('univapay');
        } catch (\Throwable $e) {
            return class_exists(LoggerFacade::class) ? LoggerFacade::create() : Application::getInstance()['eccube.logger'];
        }
    }
}
