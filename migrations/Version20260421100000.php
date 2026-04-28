<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create domain_plant table for DDD plant aggregate with workflow states';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE domain_plant (id UUID NOT NULL, organization_id UUID NOT NULL, batch_id UUID DEFAULT NULL, strain VARCHAR(255) NOT NULL, origin_type VARCHAR(16) NOT NULL, status VARCHAR(32) NOT NULL, planted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, location VARCHAR(255) NOT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_domain_plant_organization_id ON domain_plant (organization_id)');
        $this->addSql('CREATE INDEX idx_domain_plant_status ON domain_plant (status)');
        $this->addSql('CREATE INDEX idx_domain_plant_planted_at ON domain_plant (planted_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE domain_plant');
    }
}
