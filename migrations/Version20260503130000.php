<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert external_catalog_entry.markets and raw_metadata from JSON to JSONB — resolves remaining doctrine:schema:validate drift (#145).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'This migration can only run on PostgreSQL.'
        );

        foreach (['markets', 'raw_metadata'] as $column) {
            $this->addSql(sprintf(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name   = 'external_catalog_entry'
          AND column_name  = '%s'
          AND udt_name     = 'json'
    ) THEN
        ALTER TABLE external_catalog_entry ALTER COLUMN %s TYPE JSONB USING %s::jsonb;
    END IF;
END
$$;
SQL, $column, $column, $column));
        }
    }

    public function down(Schema $schema): void
    {
        // No-op: Version20260428200310 already converted these columns to JSONB
        // before this migration ran, so up() is always a no-op and down() must
        // not revert them. The JSON→JSONB ownership (and its reversal) belongs
        // to Version20260428200310::down.
    }
}
