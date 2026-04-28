<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260428200310 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reconcile remaining JSONB and column comment schema drift.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE external_catalog_entry ALTER markets TYPE JSONB USING markets::jsonb');
        $this->addSql('ALTER TABLE external_catalog_entry ALTER raw_metadata TYPE JSONB USING raw_metadata::jsonb');
        $this->addSql("COMMENT ON COLUMN genetic.metadata IS ''");
        $this->addSql("COMMENT ON COLUMN journal_entry.metadata IS ''");
        $this->addSql("COMMENT ON COLUMN alert.metadata IS ''");
        $this->addSql('ALTER TABLE report_export ALTER filters TYPE JSONB USING filters::jsonb');
        $this->addSql('ALTER TABLE report_export ALTER summary TYPE JSONB USING summary::jsonb');
        $this->addSql("COMMENT ON COLUMN report_export.filters IS ''");
        $this->addSql("COMMENT ON COLUMN report_export.summary IS ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE external_catalog_entry ALTER markets TYPE JSON USING markets::json');
        $this->addSql('ALTER TABLE external_catalog_entry ALTER raw_metadata TYPE JSON USING raw_metadata::json');
        $this->addSql("COMMENT ON COLUMN genetic.metadata IS '(DC2Type:json)'");
        $this->addSql("COMMENT ON COLUMN journal_entry.metadata IS '(DC2Type:json)'");
        $this->addSql("COMMENT ON COLUMN alert.metadata IS '(DC2Type:json)'");
        $this->addSql('ALTER TABLE report_export ALTER filters TYPE JSON USING filters::json');
        $this->addSql('ALTER TABLE report_export ALTER summary TYPE JSON USING summary::json');
        $this->addSql("COMMENT ON COLUMN report_export.filters IS '(DC2Type:json)'");
        $this->addSql("COMMENT ON COLUMN report_export.summary IS '(DC2Type:json)'");
    }
}
