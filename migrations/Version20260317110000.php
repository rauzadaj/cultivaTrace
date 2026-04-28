<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260317110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create farm table for tenant-scoped organizations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS farm (id UUID NOT NULL, tenant_id UUID NOT NULL, organization_id UUID NOT NULL, name VARCHAR(255) NOT NULL, address TEXT DEFAULT NULL, surface_m2 DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_54C1B93E2C2AC5D3 ON farm (tenant_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_54C1B93E32C8A3DE ON farm (organization_id)');
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE lower(conname) = lower('FK_54C1B93E32C8A3DE')
                ) THEN
                    ALTER TABLE farm
                        ADD CONSTRAINT FK_54C1B93E32C8A3DE
                        FOREIGN KEY (organization_id) REFERENCES organization (id) NOT DEFERRABLE INITIALLY IMMEDIATE;
                END IF;
            END
            $$;
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE farm DROP CONSTRAINT FK_54C1B93E32C8A3DE');
        $this->addSql('DROP TABLE farm');
    }
}
