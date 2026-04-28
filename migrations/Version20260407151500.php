<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407151500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add persisted reporting exports history.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE report_export (id UUID NOT NULL, generated_by_id INTEGER NOT NULL, tenant_id UUID NOT NULL, type VARCHAR(64) NOT NULL, format VARCHAR(16) NOT NULL, status VARCHAR(32) NOT NULL, file_name VARCHAR(255) NOT NULL, file_path VARCHAR(500) NOT NULL, filters JSON NOT NULL, summary JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8E164AB7B3C618F7 ON report_export (generated_by_id)');
        $this->addSql('CREATE INDEX idx_report_export_tenant_created ON report_export (tenant_id, created_at)');
        $this->addSql('COMMENT ON COLUMN report_export.filters IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN report_export.summary IS \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE report_export ADD CONSTRAINT FK_8E164AB7B3C618F7 FOREIGN KEY (generated_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report_export DROP CONSTRAINT FK_8E164AB7B3C618F7');
        $this->addSql('DROP TABLE report_export');
    }
}
