<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260312113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize cultivation metadata columns to JSONB for existing PostgreSQL databases.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration can only be executed safely on PostgreSQL.');

        $this->addSql(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'genetic'
          AND column_name = 'metadata'
          AND udt_name <> 'jsonb'
    ) THEN
        ALTER TABLE genetic ALTER COLUMN metadata TYPE JSONB USING metadata::jsonb;
    END IF;
END
$$;
SQL);
        $this->addSql(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'journal_entry'
          AND column_name = 'metadata'
          AND udt_name <> 'jsonb'
    ) THEN
        ALTER TABLE journal_entry ALTER COLUMN metadata TYPE JSONB USING metadata::jsonb;
    END IF;
END
$$;
SQL);
        $this->addSql('DROP INDEX IF EXISTS idx_genetic_metadata_gin');
        $this->addSql('DROP INDEX IF EXISTS idx_journal_entry_metadata_gin');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_genetic_metadata_gin ON genetic USING GIN (metadata)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_journal_entry_metadata_gin ON journal_entry USING GIN (metadata)');
        $this->addSql('COMMENT ON COLUMN genetic.metadata IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN journal_entry.metadata IS \'(DC2Type:json)\'');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration can only be executed safely on PostgreSQL.');

        $this->addSql('DROP INDEX IF EXISTS idx_genetic_metadata_gin');
        $this->addSql('DROP INDEX IF EXISTS idx_journal_entry_metadata_gin');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_genetic_metadata_gin ON genetic USING GIN ((metadata::jsonb))');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_journal_entry_metadata_gin ON journal_entry USING GIN ((metadata::jsonb))');
    }
}
