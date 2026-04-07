<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add persistent tenant-scoped alerts with acknowledge support.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE alert (id UUID NOT NULL, sensor_id UUID DEFAULT NULL, tenant_id UUID NOT NULL, type VARCHAR(64) NOT NULL, severity VARCHAR(32) NOT NULL, title VARCHAR(160) NOT NULL, message TEXT NOT NULL, context VARCHAR(255) DEFAULT NULL, metadata JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, acknowledged_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_7C9B057754177093 ON alert (sensor_id)');
        $this->addSql('CREATE INDEX idx_alert_tenant_ack_created ON alert (tenant_id, acknowledged_at, created_at)');
        $this->addSql('ALTER TABLE alert ADD CONSTRAINT FK_7C9B057754177093 FOREIGN KEY (sensor_id) REFERENCES sensor (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('COMMENT ON COLUMN alert.metadata IS \'(DC2Type:json)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE alert DROP CONSTRAINT FK_7C9B057754177093');
        $this->addSql('DROP TABLE alert');
    }
}
