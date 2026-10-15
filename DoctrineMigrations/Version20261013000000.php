<?php

namespace Plugin\UnivaPay\DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Columns added after 1.5.0. EC-CUBE's own schema update can't add them on an admin-upload update because that
 * request still has the 1.5.0 entity classes loaded. Fresh installs already have them, hence the hasColumn checks.
 */
final class Version20261013000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $config = $schema->getTable('plg_univa_pay_config');
        if (!$config->hasColumn('webhook_auth')) {
            $config->addColumn('webhook_auth', 'string', ['length' => 36, 'notnull' => false]);
        }

        $order = $schema->getTable('dtb_order');
        if (!$order->hasColumn('univa_pay_parent_subscription_id')) {
            $order->addColumn('univa_pay_parent_subscription_id', 'string', ['length' => 64, 'notnull' => false]);
        }
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('plg_univa_pay_config')->dropColumn('webhook_auth');
        $schema->getTable('dtb_order')->dropColumn('univa_pay_parent_subscription_id');
    }
}
