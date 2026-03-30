<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260327100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tenant isolation columns to crop and journal_entry tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crop ADD COLUMN IF NOT EXISTS tenant_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE journal_entry ADD COLUMN IF NOT EXISTS tenant_id UUID DEFAULT NULL');

        $this->addSql(<<<'SQL'
            DO $$
            DECLARE
                organization_count INTEGER;
                default_tenant UUID;
            BEGIN
                SELECT COUNT(*)
                INTO organization_count
                FROM organization;

                SELECT id
                INTO default_tenant
                FROM organization
                LIMIT 1;

                IF organization_count = 1 THEN
                    UPDATE crop
                    SET tenant_id = default_tenant
                    WHERE tenant_id IS NULL;
                END IF;
            END
            $$;
        SQL);

        $this->addSql(<<<'SQL'
            UPDATE journal_entry je
            SET tenant_id = c.tenant_id
            FROM crop c
            WHERE je.crop_id = c.id
              AND je.tenant_id IS NULL
        SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_crop_tenant_id ON crop (tenant_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_journal_entry_tenant_id ON journal_entry (tenant_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_journal_entry_tenant_id');
        $this->addSql('DROP INDEX IF EXISTS idx_crop_tenant_id');
        $this->addSql('ALTER TABLE journal_entry DROP COLUMN IF EXISTS tenant_id');
        $this->addSql('ALTER TABLE crop DROP COLUMN IF EXISTS tenant_id');
    }
}
