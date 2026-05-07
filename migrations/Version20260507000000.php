<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create health_canada_registry table for automated KYB license verification (#174)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS health_canada_registry (
            id           SERIAL PRIMARY KEY,
            license_number VARCHAR(64)  NOT NULL,
            company_name   VARCHAR(255) NOT NULL,
            license_type   VARCHAR(128) NOT NULL DEFAULT \'\',
            status         VARCHAR(32)  NOT NULL DEFAULT \'active\',
            province       VARCHAR(64)  NOT NULL DEFAULT \'\',
            issued_at      DATE         NULL,
            expires_at     DATE         NULL,
            synced_at      TIMESTAMP    NOT NULL DEFAULT NOW()
        )');

        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_hc_license_number ON health_canada_registry (license_number)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_hc_status ON health_canada_registry (status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS health_canada_registry');
    }
}
