<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Mime\Email;

/** Delivers queued effects outside their originating transaction. */
final readonly class OutboxDispatcher
{
    public function __construct(
        private Connection $connection,
        private MailerInterface $mailer,
        private HubInterface $hub,
        private StripeService $stripe,
        private LoggerInterface $logger,
    ) {
    }

    /** @return array{delivered: int, failed: int} */
    public function dispatchBatch(int $limit = 100): array
    {
        $delivered = 0;
        $failed = 0;

        for ($i = 0; $i < $limit; ++$i) {
            $message = $this->claim();
            if ($message === null) {
                break;
            }

            try {
                $this->deliver($message);
                $this->connection->executeStatement(
                    'UPDATE effect_outbox SET delivered_at = :now, claimed_at = NULL, last_error = NULL WHERE id = :id',
                    ['now' => $this->now(), 'id' => $message['id']],
                );
                ++$delivered;
            } catch (\Throwable $exception) {
                $delay = min(3600, 2 ** min(12, (int) $message['attempts']));
                $retryAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                    ->modify(sprintf('+%d seconds', $delay))->format('Y-m-d H:i:s.uP');
                $this->connection->executeStatement(
                    'UPDATE effect_outbox SET claimed_at = NULL, available_at = :retryAt, last_error = :error WHERE id = :id',
                    ['retryAt' => $retryAt, 'error' => substr($exception->getMessage(), 0, 1000), 'id' => $message['id']],
                );
                $this->logger->error('Outbox delivery failed.', [
                    'eventKey' => $message['event_key'],
                    'attempts' => $message['attempts'],
                    'exception' => $exception,
                ]);
                ++$failed;
            }
        }

        return ['delivered' => $delivered, 'failed' => $failed];
    }

    /** @return array<string, mixed>|null */
    private function claim(): ?array
    {
        $now = $this->now();
        $stale = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify('-5 minutes')->format('Y-m-d H:i:s.uP');

        $this->connection->beginTransaction();
        try {
            $sql = 'SELECT id, event_key, kind, payload, attempts FROM effect_outbox
                    WHERE delivered_at IS NULL AND available_at <= :now
                      AND (claimed_at IS NULL OR claimed_at < :stale)
                    ORDER BY created_at, id LIMIT 1';
            if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $sql .= ' FOR UPDATE SKIP LOCKED';
            }

            $message = $this->connection->fetchAssociative($sql, ['now' => $now, 'stale' => $stale]);
            if ($message === false) {
                $this->connection->commit();
                return null;
            }

            $updated = $this->connection->executeStatement(
                'UPDATE effect_outbox SET claimed_at = :now, attempts = attempts + 1
                 WHERE id = :id AND delivered_at IS NULL AND (claimed_at IS NULL OR claimed_at < :stale)',
                ['now' => $now, 'id' => $message['id'], 'stale' => $stale],
            );
            $this->connection->commit();

            if ($updated !== 1) {
                return null;
            }
            $message['attempts'] = (int) $message['attempts'] + 1;
            return $message;
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    /** @param array<string, mixed> $message */
    private function deliver(array $message): void
    {
        $payload = json_decode((string) $message['payload'], true, 512, JSON_THROW_ON_ERROR);
        if ($message['kind'] === 'email') {
            $email = (new Email())
                ->from($payload['from'])
                ->to(...$payload['to'])
                ->subject($payload['subject'])
                ->text($payload['text']);
            $email->getHeaders()->addIdHeader('Message-ID', hash('sha256', (string) $message['event_key']) . '@outbox.cultivatrace.app');
            $this->mailer->send($email);
            return;
        }

        if ($message['kind'] === 'mercure') {
            $this->hub->publish(new Update(
                topics: $payload['topics'],
                data: json_encode($payload['data'], JSON_THROW_ON_ERROR),
                private: true,
                id: (string) $message['event_key'],
            ));
            return;
        }

        if ($message['kind'] === 'stripe_sync') {
            $this->stripe->reconcilePaymentSuccess($payload['organizationId']);
            return;
        }

        throw new \UnexpectedValueException(sprintf('Unknown outbox effect kind: %s', $message['kind']));
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.uP');
    }
}
