<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store transactional external effects for replayable delivery';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE effect_outbox (
            id UUID NOT NULL PRIMARY KEY,
            tenant_id UUID NOT NULL,
            event_key VARCHAR(191) NOT NULL UNIQUE,
            kind VARCHAR(20) NOT NULL,
            payload JSONB NOT NULL,
            created_at TIMESTAMPTZ NOT NULL,
            available_at TIMESTAMPTZ NOT NULL,
            claimed_at TIMESTAMPTZ DEFAULT NULL,
            delivered_at TIMESTAMPTZ DEFAULT NULL,
            attempts INT NOT NULL DEFAULT 0,
            last_error TEXT DEFAULT NULL
        )');
        $this->addSql('CREATE INDEX idx_effect_outbox_pending ON effect_outbox (available_at, created_at) WHERE delivered_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE effect_outbox');
    }
}
