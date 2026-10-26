<?php



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
