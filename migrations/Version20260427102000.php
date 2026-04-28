<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427102000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create sensor_reading table with optional TimescaleDB hypertable support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("\n            CREATE TABLE IF NOT EXISTS sensor_reading (\n                sensor_id   UUID        NOT NULL,\n                tenant_id   UUID        NOT NULL,\n                value       FLOAT       NOT NULL,\n                recorded_at TIMESTAMPTZ NOT NULL DEFAULT NOW()\n            )\n        ");

        $this->addSql("\n            CREATE INDEX IF NOT EXISTS idx_sensor_reading\n            ON sensor_reading (sensor_id, recorded_at DESC)\n        ");

        $this->addSql("\n            DO $$\n            BEGIN\n                IF EXISTS (\n                    SELECT 1 FROM pg_extension WHERE extname = 'timescaledb'\n                ) THEN\n                    PERFORM create_hypertable(\n                        'sensor_reading', 'recorded_at',\n                        if_not_exists => TRUE\n                    );\n                END IF;\n            END\n            $$\n        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS sensor_reading');
    }
}
