<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add TimescaleDB retention (90 days) and compression (7 days) policies for sensor_reading (#126).';
    }

    public function up(Schema $schema): void
    {
        // All operations are guarded by the presence of the timescaledb extension
        // so this migration is safe to run on plain PostgreSQL installations.
        $this->addSql(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'timescaledb') THEN
        RAISE NOTICE 'TimescaleDB not installed — skipping retention/compression policies.';
        RETURN;
    END IF;

    -- Enable compression on the hypertable before adding the compression policy.
    -- SEGMENTBY groups chunks per sensor/tenant for optimal query performance.
    -- Safe to re-run: ALTER TABLE with these options is idempotent in TimescaleDB.
    BEGIN
        EXECUTE $sql$
            ALTER TABLE sensor_reading SET (
                timescaledb.compress,
                timescaledb.compress_segmentby = 'sensor_id, tenant_id',
                timescaledb.compress_orderby   = 'recorded_at DESC'
            )
        $sql$;
    EXCEPTION WHEN OTHERS THEN
        RAISE NOTICE 'Compression settings already applied or not applicable: %', SQLERRM;
    END;

    -- Retain 90 days of raw readings; older chunks are automatically dropped.
    PERFORM add_retention_policy(
        'sensor_reading',
        INTERVAL '90 days',
        if_not_exists => TRUE
    );

    -- Compress chunks older than 7 days to save storage on recent-but-cold data.
    PERFORM add_compression_policy(
        'sensor_reading',
        INTERVAL '7 days',
        if_not_exists => TRUE
    );

    RAISE NOTICE 'TimescaleDB policies applied: retention=90d, compression=7d.';
END
$$;
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'timescaledb') THEN
        RETURN;
    END IF;

    PERFORM remove_retention_policy('sensor_reading', if_exists => TRUE);
    PERFORM remove_compression_policy('sensor_reading', if_exists => TRUE);
END
$$;
SQL);
    }
}
