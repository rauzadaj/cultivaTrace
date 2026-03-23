<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260318094532 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS room (id UUID NOT NULL, tenant_id UUID NOT NULL, farm_id UUID NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, type VARCHAR(50) NOT NULL, capacity_max INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_729F519B65FCFA0D ON room (farm_id)');
        $this->addSql('CREATE TABLE IF NOT EXISTS sensor (id UUID NOT NULL, tenant_id UUID NOT NULL, room_id UUID NOT NULL, type VARCHAR(50) NOT NULL, device_id VARCHAR(255) NOT NULL, protocol VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, last_seen TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, thresholds JSON DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_BC8617B054177093 ON sensor (room_id)');
        $this->addSql('CREATE TABLE IF NOT EXISTS strain (id UUID NOT NULL, tenant_id UUID NOT NULL, name VARCHAR(255) NOT NULL, genetics VARCHAR(50) NOT NULL, cannabis_type VARCHAR(20) NOT NULL, thc_percentage DOUBLE PRECISION DEFAULT NULL, flowering_days INT DEFAULT NULL, grow_params JSON DEFAULT NULL, notes TEXT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE IF NOT EXISTS plant (id UUID NOT NULL, tenant_id UUID NOT NULL, rfid_tag VARCHAR(100) DEFAULT NULL, stage VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, germinated_at DATE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, room_id UUID NOT NULL, strain_id UUID DEFAULT NULL, created_by_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_AB030D7254177093 ON plant (room_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_AB030D7269B9E007 ON plant (strain_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_AB030D72B03A8386 ON plant (created_by_id)');
        $this->addSql('CREATE TABLE IF NOT EXISTS plant_event (id UUID NOT NULL, tenant_id UUID NOT NULL, event_type VARCHAR(100) NOT NULL, payload JSON DEFAULT NULL, notes TEXT DEFAULT NULL, photo_urls JSON DEFAULT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, hash_previous VARCHAR(64) NOT NULL, hash_self VARCHAR(64) NOT NULL, ip_address VARCHAR(45) NOT NULL, plant_id UUID NOT NULL, user_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_C874932F1D935652 ON plant_event (plant_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_C874932FA76ED395 ON plant_event (user_id)');
        $this->addSql('CREATE TABLE IF NOT EXISTS harvest_record (id UUID NOT NULL, tenant_id UUID NOT NULL, gross_weight_g NUMERIC(10, 2) NOT NULL, net_weight_g NUMERIC(10, 2) NOT NULL, harvested_at DATE NOT NULL, notes TEXT DEFAULT NULL, plant_id UUID NOT NULL, harvested_by_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_4A2D64FA1D935652 ON harvest_record (plant_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_4A2D64FABD34342D ON harvest_record (harvested_by_id)');
        $this->addSql('CREATE TABLE IF NOT EXISTS input_record (id UUID NOT NULL, tenant_id UUID NOT NULL, input_type VARCHAR(100) NOT NULL, product_name VARCHAR(255) NOT NULL, quantity NUMERIC(10, 3) NOT NULL, unit VARCHAR(50) NOT NULL, applied_at DATE NOT NULL, loq_value DOUBLE PRECISION DEFAULT NULL, loq_unit VARCHAR(20) DEFAULT NULL, loq_threshold DOUBLE PRECISION DEFAULT NULL, test_result VARCHAR(20) DEFAULT NULL, lab_name VARCHAR(255) DEFAULT NULL, quarantined_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, notes TEXT DEFAULT NULL, plant_id UUID NOT NULL, applied_by_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_578016CC1D935652 ON input_record (plant_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_578016CC4B8DEE4D ON input_record (applied_by_id)');
        $this->addSql('CREATE TABLE IF NOT EXISTS destruction_intent (id UUID NOT NULL, tenant_id UUID NOT NULL, reason TEXT NOT NULL, declared_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, legal_date_min TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(50) NOT NULL, total_weight_g NUMERIC(10, 2) DEFAULT NULL, non_cannabis_ratio NUMERIC(5, 2) DEFAULT NULL, photo_urls JSON DEFAULT NULL, confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, plant_id UUID NOT NULL, declared_by_id INT NOT NULL, confirmed_by_id INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_7A2AE4E11D935652 ON destruction_intent (plant_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_7A2AE4E1C48B85B0 ON destruction_intent (declared_by_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_7A2AE4E16F45385D ON destruction_intent (confirmed_by_id)');

        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE lower(conname) = lower('FK_729F519B65FCFA0D')) THEN
                    ALTER TABLE room ADD CONSTRAINT FK_729F519B65FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) NOT DEFERRABLE;
                END IF;
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE lower(conname) = lower('FK_BC8617B054177093')) THEN
                    ALTER TABLE sensor ADD CONSTRAINT FK_BC8617B054177093 FOREIGN KEY (room_id) REFERENCES room (id) NOT DEFERRABLE;
                END IF;
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE lower(conname) = lower('FK_AB030D7254177093')) THEN
                    ALTER TABLE plant ADD CONSTRAINT FK_AB030D7254177093 FOREIGN KEY (room_id) REFERENCES room (id) NOT DEFERRABLE;
                END IF;
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE lower(conname) = lower('FK_AB030D7269B9E007')) THEN
                    ALTER TABLE plant ADD CONSTRAINT FK_AB030D7269B9E007 FOREIGN KEY (strain_id) REFERENCES strain (id) NOT DEFERRABLE;
                END IF;
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE lower(conname) = lower('FK_AB030D72B03A8386')) THEN
                    ALTER TABLE plant ADD CONSTRAINT FK_AB030D72B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE;
                END IF;
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE lower(conname) = lower('FK_C874932F1D935652')) THEN
                    ALTER TABLE plant_event ADD CONSTRAINT FK_C874932F1D935652 FOREIGN KEY (plant_id) REFERENCES plant (id) NOT DEFERRABLE;
                END IF;
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE lower(conname) = lower('FK_C874932FA76ED395')) THEN
                    ALTER TABLE plant_event ADD CONSTRAINT FK_C874932FA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE;
                END IF;
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE lower(conname) = lower('FK_4A2D64FA1D935652')) THEN
                    ALTER TABLE harvest_record ADD CONSTRAINT FK_4A2D64FA1D935652 FOREIGN KEY (plant_id) REFERENCES plant (id) NOT DEFERRABLE;
                END IF;
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE lower(conname) = lower('FK_4A2D64FABD34342D')) THEN
                    ALTER TABLE harvest_record ADD CONSTRAINT FK_4A2D64FABD34342D FOREIGN KEY (harvested_by_id) REFERENCES "user" (id) NOT DEFERRABLE;
                END IF;
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE lower(conname) = lower('FK_578016CC1D935652')) THEN
                    ALTER TABLE input_record ADD CONSTRAINT FK_578016CC1D935652 FOREIGN KEY (plant_id) REFERENCES plant (id) NOT DEFERRABLE;
                END IF;
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE lower(conname) = lower('FK_578016CC4B8DEE4D')) THEN
                    ALTER TABLE input_record ADD CONSTRAINT FK_578016CC4B8DEE4D FOREIGN KEY (applied_by_id) REFERENCES "user" (id) NOT DEFERRABLE;
                END IF;
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE lower(conname) = lower('FK_7A2AE4E11D935652')) THEN
                    ALTER TABLE destruction_intent ADD CONSTRAINT FK_7A2AE4E11D935652 FOREIGN KEY (plant_id) REFERENCES plant (id) NOT DEFERRABLE;
                END IF;
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE lower(conname) = lower('FK_7A2AE4E1C48B85B0')) THEN
                    ALTER TABLE destruction_intent ADD CONSTRAINT FK_7A2AE4E1C48B85B0 FOREIGN KEY (declared_by_id) REFERENCES "user" (id) NOT DEFERRABLE;
                END IF;
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE lower(conname) = lower('FK_7A2AE4E16F45385D')) THEN
                    ALTER TABLE destruction_intent ADD CONSTRAINT FK_7A2AE4E16F45385D FOREIGN KEY (confirmed_by_id) REFERENCES "user" (id) NOT DEFERRABLE;
                END IF;
            END
            $$;
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE crop_activity DROP CONSTRAINT FK_25E4508B680D0B01');
        $this->addSql('ALTER TABLE destruction_intent DROP CONSTRAINT FK_7A2AE4E11D935652');
        $this->addSql('ALTER TABLE destruction_intent DROP CONSTRAINT FK_7A2AE4E1C48B85B0');
        $this->addSql('ALTER TABLE destruction_intent DROP CONSTRAINT FK_7A2AE4E16F45385D');
        $this->addSql('ALTER TABLE harvest_record DROP CONSTRAINT FK_4A2D64FA1D935652');
        $this->addSql('ALTER TABLE harvest_record DROP CONSTRAINT FK_4A2D64FABD34342D');
        $this->addSql('ALTER TABLE input_record DROP CONSTRAINT FK_578016CC1D935652');
        $this->addSql('ALTER TABLE input_record DROP CONSTRAINT FK_578016CC4B8DEE4D');
        $this->addSql('ALTER TABLE plant DROP CONSTRAINT FK_AB030D7254177093');
        $this->addSql('ALTER TABLE plant DROP CONSTRAINT FK_AB030D7269B9E007');
        $this->addSql('ALTER TABLE plant DROP CONSTRAINT FK_AB030D72B03A8386');
        $this->addSql('ALTER TABLE plant_event DROP CONSTRAINT FK_C874932F1D935652');
        $this->addSql('ALTER TABLE plant_event DROP CONSTRAINT FK_C874932FA76ED395');
        $this->addSql('ALTER TABLE room DROP CONSTRAINT FK_729F519B65FCFA0D');
        $this->addSql('ALTER TABLE sensor DROP CONSTRAINT FK_BC8617B054177093');
        $this->addSql('DROP TABLE crop_activity');
        $this->addSql('DROP TABLE destruction_intent');
        $this->addSql('DROP TABLE harvest_record');
        $this->addSql('DROP TABLE input_record');
        $this->addSql('DROP TABLE plant');
        $this->addSql('DROP TABLE plant_event');
        $this->addSql('DROP TABLE plot');
        $this->addSql('DROP TABLE room');
        $this->addSql('DROP TABLE sensor');
        $this->addSql('DROP TABLE strain');
        $this->addSql('ALTER INDEX idx_edc23d9b6ea87c7b RENAME TO idx_crop_genetic');
        $this->addSql('CREATE INDEX idx_54c1b93e2c2ac5d3 ON farm (tenant_id)');
        $this->addSql('ALTER INDEX idx_5816d04532c8a3de RENAME TO idx_54c1b93e32c8a3de');
        $this->addSql('COMMENT ON COLUMN genetic.metadata IS \'(DC2Type:json)\'');
        $this->addSql('CREATE INDEX idx_genetic_metadata_gin ON genetic (metadata)');
        $this->addSql('COMMENT ON COLUMN journal_entry.metadata IS \'(DC2Type:json)\'');
        $this->addSql('CREATE INDEX idx_journal_entry_metadata_gin ON journal_entry (metadata)');
        $this->addSql('CREATE INDEX idx_operational_service_position ON operational_service ("position")');
        $this->addSql('ALTER TABLE "user" ALTER role SET DEFAULT \'ROLE_OPERATOR\'');
        $this->addSql('ALTER INDEX uniq_8d93d649e7927c74 RENAME TO uniq_user_email');
    }
}
