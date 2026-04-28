<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260310113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create crop, genetic and append-only journal tables for the cultivation core.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE genetic (id VARCHAR(26) NOT NULL, code VARCHAR(64) NOT NULL, name VARCHAR(160) NOT NULL, vendor VARCHAR(160) DEFAULT NULL, metadata JSONB NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN genetic.metadata IS \'(DC2Type:json)\'');
        $this->addSql('CREATE UNIQUE INDEX uniq_genetic_code ON genetic (code)');
        $this->addSql('CREATE INDEX idx_genetic_metadata_gin ON genetic USING GIN (metadata)');

        $this->addSql('CREATE TABLE crop (id VARCHAR(26) NOT NULL, genetic_id VARCHAR(26) NOT NULL, batch_code VARCHAR(64) NOT NULL, display_name VARCHAR(160) NOT NULL, current_stage VARCHAR(32) NOT NULL, seeded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, harvested_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, final_yield_grams INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_crop_batch_code ON crop (batch_code)');
        $this->addSql('CREATE INDEX idx_crop_stage ON crop (current_stage)');
        $this->addSql('CREATE INDEX idx_crop_seeded_at ON crop (seeded_at)');
        $this->addSql('CREATE INDEX idx_crop_genetic ON crop (genetic_id)');
        $this->addSql('ALTER TABLE crop ADD CONSTRAINT fk_crop_genetic FOREIGN KEY (genetic_id) REFERENCES genetic (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE journal_entry (id VARCHAR(26) NOT NULL, crop_id VARCHAR(26) NOT NULL, type VARCHAR(32) NOT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, notes TEXT DEFAULT NULL, metadata JSONB NOT NULL, ph_value_centi SMALLINT DEFAULT NULL, nutrient_ppm INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN journal_entry.metadata IS \'(DC2Type:json)\'');
        $this->addSql('CREATE INDEX idx_journal_entry_occurred_at ON journal_entry (occurred_at)');
        $this->addSql('CREATE INDEX idx_journal_entry_crop_occurred_at ON journal_entry (crop_id, occurred_at)');
        $this->addSql('CREATE INDEX idx_journal_entry_metadata_gin ON journal_entry USING GIN (metadata)');
        $this->addSql('ALTER TABLE journal_entry ADD CONSTRAINT fk_journal_crop FOREIGN KEY (crop_id) REFERENCES crop (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE journal_entry DROP CONSTRAINT fk_journal_crop');
        $this->addSql('ALTER TABLE crop DROP CONSTRAINT fk_crop_genetic');
        $this->addSql('DROP TABLE journal_entry');
        $this->addSql('DROP TABLE crop');
        $this->addSql('DROP TABLE genetic');
    }
}
