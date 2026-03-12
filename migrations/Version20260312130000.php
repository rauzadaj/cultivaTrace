<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260312130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create operational service table for dashboard services CRUD.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE operational_service (id VARCHAR(26) NOT NULL, name VARCHAR(120) NOT NULL, category VARCHAR(120) NOT NULL, description VARCHAR(255) NOT NULL, icon VARCHAR(64) NOT NULL, tone VARCHAR(32) NOT NULL, status_label VARCHAR(80) NOT NULL, position INT NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_operational_service_name ON operational_service (name)');
        $this->addSql('CREATE INDEX idx_operational_service_position ON operational_service (position)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE operational_service');
    }
}
