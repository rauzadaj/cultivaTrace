<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add archived_at to farm for soft-delete semantics (#132).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE farm ADD COLUMN IF NOT EXISTS archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_farm_archived_at ON farm (archived_at) WHERE archived_at IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_farm_archived_at');
        $this->addSql('ALTER TABLE farm DROP COLUMN IF EXISTS archived_at');
    }
}
