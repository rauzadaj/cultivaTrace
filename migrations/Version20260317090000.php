<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260317090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create organization table for tenant root entity.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE organization (id UUID NOT NULL, name VARCHAR(255) NOT NULL, country VARCHAR(2) NOT NULL, plan VARCHAR(50) NOT NULL, license_status VARCHAR(50) NOT NULL, license_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE organization');
    }
}
