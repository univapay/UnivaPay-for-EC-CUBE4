<?php

namespace Plugin\UnivaPay\Entity\Master;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Entity\Master\OrderStatus;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="mtb_order_status")
 */
class UnivaPayOrderStatus extends OrderStatus
{
    public const UNIVAPAY_SUBSCRIPTION_ACTIVE = 8001;
    public const UNIVAPAY_SUBSCRIPTION_CANCEL = 8002;
    public const UNIVAPAY_SUBSCRIPTION_SUSPEND = 8003;

    public const SUBSCRIPTION_STATUSES = [
        self::UNIVAPAY_SUBSCRIPTION_ACTIVE,
        self::UNIVAPAY_SUBSCRIPTION_CANCEL,
        self::UNIVAPAY_SUBSCRIPTION_SUSPEND,
    ];
}
