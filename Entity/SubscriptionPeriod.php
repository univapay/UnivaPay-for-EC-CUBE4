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

namespace Plugin\UnivaPay\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Entity\Master\AbstractMasterEntity;

/**
 * SubscriptionPeriod
 *
 * @ORM\Table(name="plg_univa_pay_subscription_period")
 *
 * @ORM\Entity(repositoryClass="Plugin\UnivaPay\Repository\SubscriptionPeriodRepository")
 */
class SubscriptionPeriod extends AbstractMasterEntity
{
    public const NON_SUBSCRIPTION = 0;
    public const DAILY = 1;
    public const WEEKLY = 2;
    public const BIWEEKLY = 3;
    public const MONTHLY = 4;
    public const BIMONTHLY = 5;
    public const QUARTERLY = 6;
    public const SEMIANNUALLY = 7;
    public const ANNUALLY = 8;
}
