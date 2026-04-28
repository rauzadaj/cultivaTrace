<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260319133348 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add stripe_customer_id to organization';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization DROP COLUMN IF EXISTS stripe_customer_id');
    }
}
