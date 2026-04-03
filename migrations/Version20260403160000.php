<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Split supplier-sourced seed catalog rows out of genetic into external catalog storage and mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE external_catalog_entry (id VARCHAR(26) NOT NULL, source_provider VARCHAR(120) NOT NULL, external_code VARCHAR(64) NOT NULL, name VARCHAR(160) NOT NULL, vendor VARCHAR(160) DEFAULT NULL, genetics VARCHAR(255) NOT NULL, description TEXT NOT NULL, image_url VARCHAR(500) NOT NULL, source_url VARCHAR(500) NOT NULL, source_modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, markets JSON NOT NULL, raw_metadata JSON NOT NULL, ingested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_external_catalog_provider_code ON external_catalog_entry (source_provider, external_code)');
        $this->addSql('CREATE INDEX idx_external_catalog_provider ON external_catalog_entry (source_provider)');
        $this->addSql('CREATE TABLE genetic_catalog_mapping (id VARCHAR(26) NOT NULL, external_catalog_entry_id VARCHAR(26) NOT NULL, genetic_id VARCHAR(26) NOT NULL, reviewed_by_id INT DEFAULT NULL, status VARCHAR(32) NOT NULL, notes TEXT DEFAULT NULL, reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_genetic_catalog_mapping ON genetic_catalog_mapping (external_catalog_entry_id, genetic_id)');
        $this->addSql('CREATE INDEX IDX_67A16411F34034BC ON genetic_catalog_mapping (external_catalog_entry_id)');
        $this->addSql('CREATE INDEX IDX_67A16411A59B8B13 ON genetic_catalog_mapping (genetic_id)');
        $this->addSql('CREATE INDEX IDX_67A16411D6A86055 ON genetic_catalog_mapping (reviewed_by_id)');
        $this->addSql('ALTER TABLE genetic_catalog_mapping ADD CONSTRAINT FK_67A16411F34034BC FOREIGN KEY (external_catalog_entry_id) REFERENCES external_catalog_entry (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE genetic_catalog_mapping ADD CONSTRAINT FK_67A16411A59B8B13 FOREIGN KEY (genetic_id) REFERENCES genetic (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE genetic_catalog_mapping ADD CONSTRAINT FK_67A16411D6A86055 FOREIGN KEY (reviewed_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            INSERT INTO external_catalog_entry (
                id,
                source_provider,
                external_code,
                name,
                vendor,
                genetics,
                description,
                image_url,
                source_url,
                source_modified_at,
                markets,
                raw_metadata,
                ingested_at,
                updated_at
            )
            SELECT
                g.id,
                'humboldtseedcompany.com',
                g.code,
                g.name,
                g.vendor,
                COALESCE(g.metadata->>'genetics', ''),
                COALESCE(g.metadata->>'description', ''),
                COALESCE(g.metadata->>'imageUrl', ''),
                COALESCE(g.metadata->>'sourceUrl', ''),
                CASE
                    WHEN jsonb_exists(g.metadata, 'sourceModifiedAt') AND COALESCE(g.metadata->>'sourceModifiedAt', '') <> ''
                        THEN (g.metadata->>'sourceModifiedAt')::timestamp(0)
                    ELSE NULL
                END,
                COALESCE(g.metadata->'markets', '[]'::jsonb),
                g.metadata,
                NOW(),
                NOW()
            FROM genetic g
            WHERE jsonb_exists(g.metadata, 'sourceUrl')
              AND jsonb_exists(g.metadata, 'imageUrl')
              AND jsonb_exists(g.metadata, 'genetics')
              AND NOT EXISTS (
                  SELECT 1
                  FROM external_catalog_entry e
                  WHERE e.id = g.id
              )
        SQL);

        $this->addSql(<<<'SQL'
            DELETE FROM genetic g
            WHERE jsonb_exists(g.metadata, 'sourceUrl')
              AND jsonb_exists(g.metadata, 'imageUrl')
              AND jsonb_exists(g.metadata, 'genetics')
              AND NOT EXISTS (
                  SELECT 1
                  FROM crop c
                  WHERE c.genetic_id = g.id
              )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO genetic (id, code, name, vendor, metadata)
            SELECT
                e.id,
                e.external_code,
                e.name,
                e.vendor,
                e.raw_metadata
            FROM external_catalog_entry e
            WHERE NOT EXISTS (
                SELECT 1
                FROM genetic g
                WHERE g.id = e.id
            )
        SQL);

        $this->addSql('ALTER TABLE genetic_catalog_mapping DROP CONSTRAINT FK_67A16411F34034BC');
        $this->addSql('ALTER TABLE genetic_catalog_mapping DROP CONSTRAINT FK_67A16411A59B8B13');
        $this->addSql('ALTER TABLE genetic_catalog_mapping DROP CONSTRAINT FK_67A16411D6A86055');
        $this->addSql('DROP TABLE genetic_catalog_mapping');
        $this->addSql('DROP TABLE external_catalog_entry');
    }
}
