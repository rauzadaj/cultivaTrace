<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enforce NOT NULL tenant isolation on crop and journal_entry tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE journal_entry je
            SET tenant_id = c.tenant_id
            FROM crop c
            WHERE je.crop_id = c.id
              AND je.tenant_id IS NULL
              AND c.tenant_id IS NOT NULL
        SQL);

        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM crop WHERE tenant_id IS NULL) THEN
                    RAISE EXCEPTION 'Cannot enforce crop tenant isolation: rows with NULL tenant_id remain.';
                END IF;

                IF EXISTS (SELECT 1 FROM journal_entry WHERE tenant_id IS NULL) THEN
                    RAISE EXCEPTION 'Cannot enforce journal_entry tenant isolation: rows with NULL tenant_id remain.';
                END IF;
            END
            $$;
        SQL);

        $this->addSql('ALTER TABLE crop ALTER COLUMN tenant_id SET NOT NULL');
        $this->addSql('ALTER TABLE journal_entry ALTER COLUMN tenant_id SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE journal_entry ALTER COLUMN tenant_id DROP NOT NULL');
        $this->addSql('ALTER TABLE crop ALTER COLUMN tenant_id DROP NOT NULL');
    }
}
