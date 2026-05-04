<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing tables for domain models: external_catalog_entry, genetic_catalog_mapping, operational_service, domain_plant — resolves doctrine:schema:validate drift (#145).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS external_catalog_entry (
    id                 VARCHAR(26)                     NOT NULL,
    source_provider    VARCHAR(120)                    NOT NULL,
    external_code      VARCHAR(64)                     NOT NULL,
    name               VARCHAR(160)                    NOT NULL,
    vendor             VARCHAR(160)                    DEFAULT NULL,
    genetics           VARCHAR(255)                    NOT NULL,
    description        TEXT                            NOT NULL DEFAULT '',
    image_url          VARCHAR(500)                    NOT NULL,
    source_url         VARCHAR(500)                    NOT NULL,
    source_modified_at TIMESTAMP(0) WITHOUT TIME ZONE  DEFAULT NULL,
    markets            JSONB                           NOT NULL DEFAULT '[]',
    raw_metadata       JSONB                           NOT NULL DEFAULT '{}',
    ingested_at        TIMESTAMP(0) WITHOUT TIME ZONE  NOT NULL,
    updated_at         TIMESTAMP(0) WITHOUT TIME ZONE  NOT NULL,
    PRIMARY KEY (id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_external_catalog_provider ON external_catalog_entry (source_provider)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_external_catalog_provider_code ON external_catalog_entry (source_provider, external_code)');

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS genetic_catalog_mapping (
    id                        VARCHAR(26)                     NOT NULL,
    external_catalog_entry_id VARCHAR(26)                     NOT NULL,
    genetic_id                VARCHAR(26)                     NOT NULL,
    reviewed_by_id            UUID                            DEFAULT NULL,
    status                    VARCHAR(32)                     NOT NULL DEFAULT 'pending',
    notes                     TEXT                            DEFAULT NULL,
    reviewed_at               TIMESTAMP(0) WITHOUT TIME ZONE  DEFAULT NULL,
    created_at                TIMESTAMP(0) WITHOUT TIME ZONE  NOT NULL,
    updated_at                TIMESTAMP(0) WITHOUT TIME ZONE  NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT FK_C723A6E193A94A09 FOREIGN KEY (external_catalog_entry_id)
        REFERENCES external_catalog_entry (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT FK_C723A6E16EA87C7B FOREIGN KEY (genetic_id)
        REFERENCES genetic (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT FK_C723A6E1FC6B21F1 FOREIGN KEY (reviewed_by_id)
        REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_67A16411F34034BC ON genetic_catalog_mapping (external_catalog_entry_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_67A16411A59B8B13 ON genetic_catalog_mapping (genetic_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_67A16411D6A86055 ON genetic_catalog_mapping (reviewed_by_id)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_genetic_catalog_mapping ON genetic_catalog_mapping (external_catalog_entry_id, genetic_id)');

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS operational_service (
    id           VARCHAR(26)                     NOT NULL,
    name         VARCHAR(120)                    NOT NULL,
    category     VARCHAR(120)                    NOT NULL,
    description  VARCHAR(255)                    NOT NULL,
    icon         VARCHAR(64)                     NOT NULL,
    tone         VARCHAR(32)                     NOT NULL DEFAULT 'primary',
    status_label VARCHAR(80)                     NOT NULL,
    position     INTEGER                         NOT NULL DEFAULT 0,
    updated_at   TIMESTAMP(0) WITHOUT TIME ZONE  NOT NULL,
    PRIMARY KEY (id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_operational_service_position ON operational_service (position)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_operational_service_name ON operational_service (name)');

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS domain_plant (
    id              UUID                            NOT NULL,
    organization_id UUID                            NOT NULL,
    batch_id        UUID                            DEFAULT NULL,
    strain          VARCHAR(255)                    NOT NULL,
    origin_type     VARCHAR(16)                     NOT NULL,
    status          VARCHAR(32)                     NOT NULL,
    planted_at      TIMESTAMP(0) WITHOUT TIME ZONE  NOT NULL,
    location        VARCHAR(255)                    NOT NULL,
    notes           TEXT                            DEFAULT NULL,
    created_at      TIMESTAMP(0) WITHOUT TIME ZONE  NOT NULL,
    updated_at      TIMESTAMP(0) WITHOUT TIME ZONE  NOT NULL,
    PRIMARY KEY (id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_domain_plant_organization_id ON domain_plant (organization_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_domain_plant_status ON domain_plant (status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_domain_plant_planted_at ON domain_plant (planted_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS genetic_catalog_mapping');
        $this->addSql('DROP TABLE IF EXISTS external_catalog_entry');
        $this->addSql('DROP TABLE IF EXISTS operational_service');
        $this->addSql('DROP TABLE IF EXISTS domain_plant');
    }
}
