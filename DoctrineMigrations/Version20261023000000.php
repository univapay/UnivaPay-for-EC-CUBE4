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

namespace Plugin\UnivaPay\DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $order = $schema->getTable('dtb_order');

        if (!$order->hasColumn('univa_pay_subscription_period')) {
            $this->addSql('ALTER TABLE dtb_order ADD univa_pay_subscription_period INT DEFAULT NULL');
        }
        if (!$order->hasColumn('univa_pay_next_charge_date')) {
            $this->addSql('ALTER TABLE dtb_order ADD univa_pay_next_charge_date DATE DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $order = $schema->getTable('dtb_order');

        if ($order->hasColumn('univa_pay_next_charge_date')) {
            $this->addSql('ALTER TABLE dtb_order DROP COLUMN univa_pay_next_charge_date');
        }
        if ($order->hasColumn('univa_pay_subscription_period')) {
            $this->addSql('ALTER TABLE dtb_order DROP COLUMN univa_pay_subscription_period');
        }
    }
}
