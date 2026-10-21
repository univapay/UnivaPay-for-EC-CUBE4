<?php

namespace Plugin\UnivaPay\DoctrineMigrations;

use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Columns added after 1.5.0. EC-CUBE's own schema update can't add them on an admin-upload update because that
 * request still has the 1.5.0 entity classes loaded. Fresh installs already have them, hence the hasColumn checks.
 */
class Version20261013000000 extends AbstractMigration
{
    private const INDEX_NAME = 'uniq_univa_pay_charge_id';

    public function up(Schema $schema): void
    {
        // Charge uniqueness check migration
        // Check for an existing unique index on the charge ID
        $order = $schema->getTable('dtb_order');
        $hasUniqueIndex = false;

        foreach ($order->getIndexes() as $index) {
            if (
                $index->isUnique() &&
                $index->getColumns() === ['univa_pay_charge_id']
            ) {
                $hasUniqueIndex = true;
                break;
            }
        }

        if (!$hasUniqueIndex) {
            $index = new Index(
                self::INDEX_NAME,
                ['univa_pay_charge_id'],
                true
            );

            $this->addSql(
                $this->connection->getDatabasePlatform()
                    ->getCreateIndexSQL($index, 'dtb_order')
            );
        }

        // Subscription parent migration
        $config = $schema->getTable('plg_univa_pay_config');
        if (!$config->hasColumn('webhook_auth')) {
            $config->addColumn('webhook_auth', 'string', ['length' => 36, 'notnull' => false]);
        }
        if (!$config->hasColumn('customer_url')) {
            $config->addColumn('customer_url', 'string', ['length' => 1024, 'notnull' => false]);
        }

        $order = $schema->getTable('dtb_order');
        if (!$order->hasColumn('univa_pay_parent_subscription_id')) {
            $order->addColumn('univa_pay_parent_subscription_id', 'string', ['length' => 64, 'notnull' => false]);
        }
    }

    public function down(Schema $schema): void
    {
        // Subscription parent migration
        $config = $schema->getTable('plg_univa_pay_config');
        $config->dropColumn('webhook_auth');
        $config->dropColumn('customer_url');
        $schema->getTable('dtb_order')->dropColumn('univa_pay_parent_subscription_id');

        // Charge uniqueness check migration
        if ($schema->getTable('dtb_order')->hasIndex(self::INDEX_NAME)) {
            $this->addSql(
                $this->connection->getDatabasePlatform()
                    ->getDropIndexSQL(self::INDEX_NAME, 'dtb_order')
            );
        }
    }
}
