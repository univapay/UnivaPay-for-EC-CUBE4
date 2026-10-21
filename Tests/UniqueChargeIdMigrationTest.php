<?php

namespace Plugin\UnivaPay\Tests\DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use Plugin\UnivaPay\DoctrineMigrations\Version20261021000000;

class ChargeIdUniqueMigrationTest extends TestCase
{
    public function platforms()
    {
        return [
            'MySQL' => [new MySqlPlatform()],
            'PostgreSQL' => [new PostgreSqlPlatform()],
        ];
    }

    /** @dataProvider platforms */
    public function testAddsUniqueIndex(AbstractPlatform $platform)
    {
        $schema = $this->orderSchema();

        // Existing non-unique index must not prevent migration.
        $schema->getTable('dtb_order')->addIndex(
            ['univa_pay_charge_id'],
            'old_charge_index'
        );

        $sql = $this->migrationSql('up', $schema, $platform);

        $this->assertCount(1, $sql);
        $this->assertSqlContains(
            'CREATE UNIQUE INDEX',
            strtoupper($sql[0])
        );
        $this->assertSqlContains(
            'uniq_univa_pay_charge_id',
            $sql[0]
        );
        $this->assertSqlContains('dtb_order', $sql[0]);
        $this->assertSqlContains(
            'univa_pay_charge_id',
            $sql[0]
        );
    }

    /** @dataProvider platforms */
    public function testSkipsExistingUniqueIndex(AbstractPlatform $platform)
    {
        $schema = $this->orderSchema();

        $schema->getTable('dtb_order')->addUniqueIndex(
            ['univa_pay_charge_id'],
            'already_unique'
        );

        $this->assertSame(
            [],
            $this->migrationSql('up', $schema, $platform)
        );
    }

    /** @dataProvider platforms */
    public function testDownDropsMigrationIndex(AbstractPlatform $platform)
    {
        $schema = $this->orderSchema();

        $schema->getTable('dtb_order')->addUniqueIndex(
            ['univa_pay_charge_id'],
            'uniq_univa_pay_charge_id'
        );

        $sql = $this->migrationSql('down', $schema, $platform);

        $this->assertCount(1, $sql);
        $this->assertSqlContains(
            'DROP INDEX',
            strtoupper($sql[0])
        );
        $this->assertSqlContains(
            'uniq_univa_pay_charge_id',
            $sql[0]
        );
    }

    /** @dataProvider platforms */
    public function testDownPreservesOtherUniqueIndexes(
        AbstractPlatform $platform
    ) {
        $schema = $this->orderSchema();

        $schema->getTable('dtb_order')->addUniqueIndex(
            ['univa_pay_charge_id'],
            'already_unique'
        );

        $this->assertSame(
            [],
            $this->migrationSql('down', $schema, $platform)
        );
    }

    private function orderSchema(): Schema
    {
        $schema = new Schema();

        $schema->createTable('dtb_order')->addColumn(
            'univa_pay_charge_id',
            'string',
            ['notnull' => false]
        );

        return $schema;
    }

    // Helper because PHPUnit 6.x doesn't seem to support assertStringContainsString(), which was introduced in PHPUnit 7.5
    private function assertSqlContains($needle, $sql)
    {
        $this->assertTrue(
            strpos($sql, $needle) !== false,
            sprintf(
                'Expected SQL to contain "%s", got: %s',
                $needle,
                $sql
            )
        );
    }

    private function migrationSql(
        string $direction,
        Schema $schema,
        AbstractPlatform $platform
    ): array {
        $connection = $this->createMock(Connection::class);

        $connection->method('getDatabasePlatform')
            ->willReturn($platform);

        // Capture the actual SQL queued by the migration.
        $migration = new class($connection) extends Version20261021000000 {
            private $statements = [];

            public function __construct(Connection $connection)
            {
                $this->connection = $connection;
            }

            protected function addSql(
                $sql,
                array $params = [],
                array $types = []
            ) {
                $this->statements[] = $sql;
            }

            public function statements(): array
            {
                return $this->statements;
            }
        };

        $migration->$direction($schema);

        return $migration->statements();
    }
}
