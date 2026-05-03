<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Convert all remaining JSON columns to JSONB so that Doctrine 3 schema
 * introspection matches the entity mapping and doctrine:schema:validate passes
 * on a clean install.  All ALTER statements are wrapped in DO blocks so the
 * migration is idempotent and safe to run on databases that have already been
 * partially migrated.
 */
final class Version20260503100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert remaining JSON columns to JSONB — resolve doctrine:schema:validate drift (#145).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'This migration can only run on PostgreSQL.'
        );

        // Columns that Doctrine 3 maps to JSONB but earlier migrations left as JSON.
        $conversions = [
            ['"user"',               'roles'],
            ['organization_invitation', 'roles'],
            ['sensor',               'thresholds'],
            ['strain',               'grow_params'],
            ['plant_event',          'payload'],
            ['plant_event',          'photo_urls'],
            ['destruction_intent',   'photo_urls'],
        ];

        foreach ($conversions as [$table, $column]) {
            $this->addSql(sprintf(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name   = %s
          AND column_name  = '%s'
          AND udt_name     = 'json'
    ) THEN
        ALTER TABLE %s ALTER COLUMN %s TYPE JSONB USING %s::jsonb;
    END IF;
END
$$;
SQL,
                $this->quoteStrLiteral($table),
                $column,
                $table,
                $column,
                $column
            ));
        }

        // Ensure the refresh_token FK is declared with INITIALLY IMMEDIATE so
        // that Doctrine-generated schema matches the migration exactly.
        $this->addSql(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_name = 'FK_C74F2195A76ED395'
          AND table_name      = 'refresh_token'
    ) THEN
        ALTER TABLE refresh_token
            DROP CONSTRAINT FK_C74F2195A76ED395;
    END IF;

    ALTER TABLE refresh_token
        ADD CONSTRAINT FK_C74F2195A76ED395
        FOREIGN KEY (user_id)
        REFERENCES "user" (id)
        ON DELETE CASCADE
        NOT DEFERRABLE INITIALLY IMMEDIATE;
END
$$;
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'This migration can only run on PostgreSQL.'
        );

        $reversions = [
            ['"user"',               'roles'],
            ['organization_invitation', 'roles'],
            ['sensor',               'thresholds'],
            ['strain',               'grow_params'],
            ['plant_event',          'payload'],
            ['plant_event',          'photo_urls'],
            ['destruction_intent',   'photo_urls'],
        ];

        foreach ($reversions as [$table, $column]) {
            $this->addSql(sprintf(
                'ALTER TABLE %s ALTER COLUMN %s TYPE JSON USING %s::json',
                $table,
                $column,
                $column
            ));
        }
    }

    private function quoteStrLiteral(string $table): string
    {
        // Strip surrounding double-quotes if present so the table name can be
        // used inside a PL/pgSQL string literal for information_schema queries.
        return "'" . trim($table, '"') . "'";
    }
}
