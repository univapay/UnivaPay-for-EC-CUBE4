<?php

namespace Plugin\UnivaPay\Util;

class UnivaPayLogger
{
    public static function get()
    {
        return logs('univapay');
    }
}
