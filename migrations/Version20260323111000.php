<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260323111000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create license_document table for KYB workflow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS license_document (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                organization_id UUID NOT NULL,
                license_number VARCHAR(255) NOT NULL,
                license_type VARCHAR(50) NOT NULL,
                file_path VARCHAR(500) DEFAULT NULL,
                status VARCHAR(50) NOT NULL,
                license_expires_at DATE DEFAULT NULL,
                submitted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                verification_method VARCHAR(50) DEFAULT NULL,
                rejection_reason TEXT DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_24391B4132C8A3DE ON license_document (organization_id)');
        $this->addSql('ALTER TABLE license_document ADD CONSTRAINT FK_24391B4132C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE license_document DROP CONSTRAINT IF EXISTS FK_24391B4132C8A3DE');
        $this->addSql('DROP TABLE IF EXISTS license_document');
    }
}
