<?php

namespace Plugin\UnivaPay\DoctrineMigrations;

use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


// This migration checks for historical duplicates before upgrading
class Version20261021000000 extends AbstractMigration
{
    private const INDEX_NAME = 'uniq_univa_pay_charge_id';

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('dtb_order');

        foreach ($table->getIndexes() as $index) {
            if (
                $index->isUnique() &&
                $index->getColumns() === ['univa_pay_charge_id']
            ) {
                return;
            }
        }

        $index = new Index(
            self::INDEX_NAME,
            ['univa_pay_charge_id'],
            true
        );

        // addSql() queues the DDL for execution
        // https://www.doctrine-project.org/projects/doctrine-migrations/en/4.0/reference/migration-classes.html#addsql
        $this->addSql(
            $this->connection->getDatabasePlatform()
                ->getCreateIndexSQL($index, 'dtb_order')
        );
    }

    public function down(Schema $schema): void
    {
        if ($schema->getTable('dtb_order')->hasIndex(self::INDEX_NAME)) {
            $this->addSql(
                $this->connection->getDatabasePlatform()
                    ->getDropIndexSQL(self::INDEX_NAME, 'dtb_order')
            );
        }
    }
}
