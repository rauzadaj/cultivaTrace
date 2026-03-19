<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;

/**
 * SensorReadingRepository — requêtes DBAL natif sur TimescaleDB.
 *
 * La table sensor_reading est une TimescaleDB hypertable.
 * On n'utilise PAS Doctrine ORM ici pour les performances.
 *
 * Schema de la table (créée via migration manuelle) :
 *   CREATE TABLE sensor_reading (
 *     sensor_id   UUID        NOT NULL,
 *     tenant_id   UUID        NOT NULL,
 *     value       FLOAT       NOT NULL,
 *     recorded_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
 *   );
 *   SELECT create_hypertable('sensor_reading', 'recorded_at');
 *   CREATE INDEX ON sensor_reading (sensor_id, recorded_at DESC);
 *
 * Si TimescaleDB n'est pas disponible, la table fonctionne
 * comme une table PostgreSQL classique (moins performante sur 90j+).
 */
class SensorReadingRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    /**
     * Insère une nouvelle lecture capteur.
     */
    public function insert(string $sensorId, string $tenantId, float $value): void
    {
        $this->connection->executeStatement(
            'INSERT INTO sensor_reading (sensor_id, tenant_id, value, recorded_at)
             VALUES (:sensorId, :tenantId, :value, NOW())',
            [
                'sensorId' => $sensorId,
                'tenantId' => $tenantId,
                'value'    => $value,
            ]
        );
    }

    /**
     * Dernière lecture d'un capteur.
     */
    public function findLatest(string $sensorId): ?array
    {
        return $this->connection->fetchAssociative(
            'SELECT value, recorded_at
             FROM sensor_reading
             WHERE sensor_id = :sensorId
             ORDER BY recorded_at DESC
             LIMIT 1',
            ['sensorId' => $sensorId]
        ) ?: null;
    }

    /**
     * Historique agrégé selon la période.
     * Utilise time_bucket de TimescaleDB si disponible.
     *
     * @return array<array{bucket: string, avg_value: float, min_value: float, max_value: float}>
     */
    public function findHistory(string $sensorId, string $period = '30d'): array
    {
        [$days, $bucket] = match ($period) {
            '7d'   => [7,   '1 hour'],
            '30d'  => [30,  '3 hours'],
            '90d'  => [90,  '12 hours'],
            '365d' => [365, '1 day'],
            default => [30, '3 hours'],
        };
        $since = (new \DateTimeImmutable(sprintf('-%d days', $days)))->format(\DateTimeInterface::ATOM);

        // Essayer TimescaleDB time_bucket, fallback sur date_trunc
        try {
            return $this->connection->fetchAllAssociative(
                "SELECT
                    time_bucket(:bucket, recorded_at) AS bucket,
                    AVG(value)  AS avg_value,
                    MIN(value)  AS min_value,
                    MAX(value)  AS max_value
                 FROM sensor_reading
                 WHERE sensor_id = :sensorId
                   AND recorded_at > :since
                 GROUP BY bucket
                 ORDER BY bucket ASC",
                [
                    'bucket'   => $bucket,
                    'sensorId' => $sensorId,
                    'since'    => $since,
                ]
            );
        } catch (\Exception) {
            // Fallback PostgreSQL classique si TimescaleDB absent
            return $this->connection->fetchAllAssociative(
                "SELECT
                    date_trunc('hour', recorded_at) AS bucket,
                    AVG(value) AS avg_value,
                    MIN(value) AS min_value,
                    MAX(value) AS max_value
                 FROM sensor_reading
                 WHERE sensor_id = :sensorId
                   AND recorded_at > :since
                 GROUP BY bucket
                 ORDER BY bucket ASC",
                [
                    'sensorId' => $sensorId,
                    'since'    => $since,
                ]
            );
        }
    }

    /**
     * Toutes les lectures d'une room sur les dernières X minutes.
     * Utilisé par le dashboard temps réel.
     *
     * @return array<array{sensor_id: string, value: float, recorded_at: string}>
     */
    public function findRecentForRoom(string $roomId, int $minutes = 5): array
    {
        $since = (new \DateTimeImmutable(sprintf('-%d minutes', $minutes)))->format(\DateTimeInterface::ATOM);

        return $this->connection->fetchAllAssociative(
            'SELECT sr.sensor_id, sr.value, sr.recorded_at
             FROM sensor_reading sr
             INNER JOIN sensor s ON s.id = sr.sensor_id
             WHERE s.room_id = :roomId
               AND sr.recorded_at > :since
             ORDER BY sr.recorded_at DESC',
            [
                'roomId' => $roomId,
                'since'  => $since,
            ]
        );
    }
}
