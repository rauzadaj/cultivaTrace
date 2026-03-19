<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_SensorReading extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create sensor_reading table with optional TimescaleDB hypertable support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS sensor_reading (
                sensor_id   UUID        NOT NULL,
                tenant_id   UUID        NOT NULL,
                value       FLOAT       NOT NULL,
                recorded_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");

        $this->addSql("
            CREATE INDEX IF NOT EXISTS idx_sensor_reading
            ON sensor_reading (sensor_id, recorded_at DESC)
        ");

        $this->addSql("
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM pg_extension WHERE extname = 'timescaledb'
                ) THEN
                    PERFORM create_hypertable(
                        'sensor_reading', 'recorded_at',
                        if_not_exists => TRUE
                    );
                END IF;
            END
            $$
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS sensor_reading');
    }
}
