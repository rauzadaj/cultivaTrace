<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;

/** Stores effects on the same connection as the business transaction. */
class TransactionalOutbox
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function queueEmail(string $tenantId, string $eventKey, Email $email): void
    {
        $this->enqueue($tenantId, $eventKey, 'email', [
            'from' => $email->getFrom()[0]->getAddress(),
            'to' => array_map(static fn ($address): string => $address->getAddress(), $email->getTo()),
            'subject' => $email->getSubject(),
            'text' => $email->getTextBody(),
        ]);
    }

    /**
     * @param list<string> $topics
     * @param array<string, mixed> $data
     */
    public function queueMercure(string $tenantId, string $eventKey, array $topics, array $data): void
    {
        $this->enqueue($tenantId, $eventKey, 'mercure', ['topics' => $topics, 'data' => $data]);
    }

    public function queueStripeSync(string $tenantId, string $eventKey, string $organizationId): void
    {
        $this->enqueue($tenantId, $eventKey, 'stripe_sync', ['organizationId' => $organizationId]);
    }

    /** @param array<string, mixed> $payload */
    private function enqueue(string $tenantId, string $eventKey, string $kind, array $payload): void
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.uP');
        $this->connection->executeStatement(
            'INSERT INTO effect_outbox (id, tenant_id, event_key, kind, payload, created_at, available_at, attempts)
             VALUES (:id, :tenantId, :eventKey, :kind, :payload, :now, :now, 0)
             ON CONFLICT (event_key) DO NOTHING',
            [
                'id' => (string) Uuid::v4(),
                'tenantId' => $tenantId,
                'eventKey' => $eventKey,
                'kind' => $kind,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'now' => $now,
            ],
        );
    }
}
