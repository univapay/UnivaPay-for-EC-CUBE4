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
    public const UNIVAPAY_SUBSCRIPTION_ACTIVE = 100;
    public const UNIVAPAY_SUBSCRIPTION_CANCEL = 101;
    public const UNIVAPAY_SUBSCRIPTION_SUSPEND = 102;
}
