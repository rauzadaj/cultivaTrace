<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;

/**
 * SensorReadingRepository — native DBAL queries on TimescaleDB.
 *
 * The sensor_reading table is a TimescaleDB hypertable.
 * Doctrine ORM is NOT used here for performance reasons.
 *
 * Table schema (created via manual migration):
 *   CREATE TABLE sensor_reading (
 *     sensor_id   UUID        NOT NULL,
 *     tenant_id   UUID        NOT NULL,
 *     value       FLOAT       NOT NULL,
 *     recorded_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
 *   );
 *   SELECT create_hypertable('sensor_reading', 'recorded_at');
 *   CREATE INDEX ON sensor_reading (sensor_id, recorded_at DESC);
 *
 * If TimescaleDB is unavailable, the table works as a standard
 * PostgreSQL table (less performant beyond 90 days).
 */
class SensorReadingRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    /**
     * Inserts a new sensor reading.
     */
    public function insert(string $sensorId, string $tenantId, float $value): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        // SQLite stores dates as plain TEXT — no offset understood in comparisons.
        // PostgreSQL TIMESTAMPTZ requires the offset so the session timezone is not
        // applied: without it, a PHP runtime in Europe/Paris would store an instant
        // 1–2 hours off from the true UTC value.
        $isSQLite = $this->connection->getDatabasePlatform() instanceof SQLitePlatform;
        $recordedAt = $isSQLite
            ? $now->format('Y-m-d H:i:s')
            : $now->format('Y-m-d H:i:sP');

        $this->connection->executeStatement(
            'INSERT INTO sensor_reading (sensor_id, tenant_id, value, recorded_at)
             VALUES (:sensorId, :tenantId, :value, :recordedAt)',
            [
                'sensorId'   => $sensorId,
                'tenantId'   => $tenantId,
                'value'      => $value,
                'recordedAt' => $recordedAt,
            ]
        );
    }

    /**
     * Latest reading for a sensor.
     *
     * @return array<string, mixed>|null
     */
    public function findLatest(string $sensorId, string $tenantId): ?array
    {
        return $this->connection->fetchAssociative(
            'SELECT value, recorded_at
             FROM sensor_reading
             WHERE sensor_id = :sensorId
               AND tenant_id = :tenantId
             ORDER BY recorded_at DESC
             LIMIT 1',
            ['sensorId' => $sensorId, 'tenantId' => $tenantId]
        ) ?: null;
    }

    /**
     * Aggregated history for the given period.
     * Uses TimescaleDB time_bucket if available.
     *
     * @return array<array{bucket: string, avg_value: float, min_value: float, max_value: float}>
     */
    public function findHistory(string $sensorId, string $tenantId, string $period = '30d'): array
    {
        [$days, $bucket] = match ($period) {
            '7d'   => [7,   '1 hour'],
            '30d'  => [30,  '3 hours'],
            '90d'  => [90,  '12 hours'],
            '365d' => [365, '1 day'],
            default => [30, '3 hours'],
        };
        $isSQLite = $this->connection->getDatabasePlatform() instanceof SQLitePlatform;

        $sinceUtc = new \DateTimeImmutable(sprintf('-%d days', $days), new \DateTimeZone('UTC'));
        $since = $isSQLite ? $sinceUtc->format('Y-m-d H:i:s') : $sinceUtc->format('Y-m-d H:i:sP');

        if ($isSQLite) {
            return $this->connection->fetchAllAssociative(
                "SELECT
                    strftime('%Y-%m-%d %H:00:00', recorded_at) AS bucket,
                    AVG(value) AS avg_value,
                    MIN(value) AS min_value,
                    MAX(value) AS max_value
                 FROM sensor_reading
                 WHERE sensor_id = :sensorId
                   AND tenant_id = :tenantId
                   AND recorded_at > :since
                 GROUP BY strftime('%Y-%m-%d %H:00:00', recorded_at)
                 ORDER BY bucket ASC",
                [
                    'sensorId' => $sensorId,
                    'tenantId' => $tenantId,
                    'since'    => $since,
                ]
            );
        }

        // Try TimescaleDB time_bucket, fall back to date_trunc
        try {
            return $this->connection->fetchAllAssociative(
                "SELECT
                    time_bucket(:bucket, recorded_at) AS bucket,
                    AVG(value)  AS avg_value,
                    MIN(value)  AS min_value,
                    MAX(value)  AS max_value
                 FROM sensor_reading
                 WHERE sensor_id = :sensorId
                   AND tenant_id = :tenantId
                   AND recorded_at > :since
                 GROUP BY bucket
                 ORDER BY bucket ASC",
                [
                    'bucket'   => $bucket,
                    'sensorId' => $sensorId,
                    'tenantId' => $tenantId,
                    'since'    => $since,
                ]
            );
        } catch (\Exception) {
            // Standard PostgreSQL fallback when TimescaleDB is unavailable
            return $this->connection->fetchAllAssociative(
                "SELECT
                    date_trunc('hour', recorded_at) AS bucket,
                    AVG(value) AS avg_value,
                    MIN(value) AS min_value,
                    MAX(value) AS max_value
                 FROM sensor_reading
                 WHERE sensor_id = :sensorId
                   AND tenant_id = :tenantId
                   AND recorded_at > :since
                 GROUP BY bucket
                 ORDER BY bucket ASC",
                [
                    'sensorId' => $sensorId,
                    'tenantId' => $tenantId,
                    'since'    => $since,
                ]
            );
        }
    }

    /**
     * All readings for a room over the last X minutes.
     * Used by the real-time dashboard.
     *
     * @return array<array{sensor_id: string, value: float, recorded_at: string}>
     */
    public function findRecentForRoom(string $roomId, string $tenantId, int $minutes = 5): array
    {
        $since = (new \DateTimeImmutable(sprintf('-%d minutes', $minutes)))->format(\DateTimeInterface::ATOM);

        return $this->connection->fetchAllAssociative(
            'SELECT sr.sensor_id, sr.value, sr.recorded_at
             FROM sensor_reading sr
             INNER JOIN sensor s ON s.id = sr.sensor_id
             WHERE s.room_id = :roomId
               AND sr.tenant_id = :tenantId
               AND sr.recorded_at > :since
             ORDER BY sr.recorded_at DESC',
            [
                'roomId'   => $roomId,
                'tenantId' => $tenantId,
                'since'    => $since,
            ]
        );
    }
}
