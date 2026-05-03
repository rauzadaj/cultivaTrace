<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260319100102 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing tenant_id, type, device_id, protocol, status, last_seen and thresholds columns to sensor table (idempotent guards).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'public' AND table_name = 'sensor' AND column_name = 'tenant_id'
                ) THEN
                    ALTER TABLE sensor ADD tenant_id UUID NOT NULL;
                END IF;
            END
            $$;
        SQL);
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'public' AND table_name = 'sensor' AND column_name = 'type'
                ) THEN
                    ALTER TABLE sensor ADD type VARCHAR(50) NOT NULL;
                END IF;
            END
            $$;
        SQL);
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'public' AND table_name = 'sensor' AND column_name = 'device_id'
                ) THEN
                    ALTER TABLE sensor ADD device_id VARCHAR(255) NOT NULL;
                END IF;
            END
            $$;
        SQL);
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'public' AND table_name = 'sensor' AND column_name = 'protocol'
                ) THEN
                    ALTER TABLE sensor ADD protocol VARCHAR(50) NOT NULL;
                END IF;
            END
            $$;
        SQL);
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'public' AND table_name = 'sensor' AND column_name = 'status'
                ) THEN
                    ALTER TABLE sensor ADD status VARCHAR(50) NOT NULL;
                END IF;
            END
            $$;
        SQL);
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'public' AND table_name = 'sensor' AND column_name = 'last_seen'
                ) THEN
                    ALTER TABLE sensor ADD last_seen TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL;
                END IF;
            END
            $$;
        SQL);
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'public' AND table_name = 'sensor' AND column_name = 'thresholds'
                ) THEN
                    ALTER TABLE sensor ADD thresholds JSON DEFAULT NULL;
                END IF;
            END
            $$;
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sensor DROP tenant_id');
        $this->addSql('ALTER TABLE sensor DROP type');
        $this->addSql('ALTER TABLE sensor DROP device_id');
        $this->addSql('ALTER TABLE sensor DROP protocol');
        $this->addSql('ALTER TABLE sensor DROP status');
        $this->addSql('ALTER TABLE sensor DROP last_seen');
        $this->addSql('ALTER TABLE sensor DROP thresholds');
    }
}
