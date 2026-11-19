<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\TransactionalOutbox;
use App\Service\OutboxDispatcher;
use App\Service\StripeService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Mime\Email;

final class TransactionalOutboxTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement('CREATE TABLE effect_outbox (
            id TEXT PRIMARY KEY, tenant_id TEXT NOT NULL, event_key TEXT NOT NULL UNIQUE,
            kind TEXT NOT NULL, payload TEXT NOT NULL, created_at TEXT NOT NULL,
            available_at TEXT NOT NULL, claimed_at TEXT, delivered_at TEXT,
            attempts INTEGER NOT NULL DEFAULT 0, last_error TEXT
        )');
    }

    public function testRollbackDiscardsTheBusinessEffect(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        $outbox = $this->outbox();

        $this->connection->beginTransaction();
        $outbox->queueEmail('tenant-1', 'kyb-active-license-1', $this->email());
        $this->connection->rollBack();

        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM effect_outbox'));
        self::assertSame(['delivered' => 0, 'failed' => 0], $this->dispatcher($mailer)->dispatchBatch());
    }

    public function testFailedDeliveryIsRetryableAndKeepsTheSameMessageId(): void
    {
        $messageIds = [];
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(2))->method('send')->willReturnCallback(
            static function (Email $email) use (&$messageIds): void {
                $messageIds[] = $email->getHeaders()->get('Message-ID')?->getBodyAsString();
                if (count($messageIds) === 1) {
                    throw new \RuntimeException('Mailer unavailable');
                }
            },
        );
        $outbox = $this->outbox();
        $outbox->queueEmail('tenant-1', 'billing-payment-1', $this->email());
        $dispatcher = $this->dispatcher($mailer);

        self::assertSame(['delivered' => 0, 'failed' => 1], $dispatcher->dispatchBatch());
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT attempts FROM effect_outbox'));
        self::assertNull($this->connection->fetchOne('SELECT delivered_at FROM effect_outbox'));

        $this->connection->executeStatement("UPDATE effect_outbox SET available_at = '2000-01-01 00:00:00+00:00'");
        self::assertSame(['delivered' => 1, 'failed' => 0], $dispatcher->dispatchBatch());
        self::assertSame($messageIds[0], $messageIds[1]);
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT attempts FROM effect_outbox'));
        self::assertNotNull($this->connection->fetchOne('SELECT delivered_at FROM effect_outbox'));
        self::assertSame(['delivered' => 0, 'failed' => 0], $dispatcher->dispatchBatch());
    }

    public function testDuplicateEnqueuePublishesOneMercureUpdateWithStableId(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())->method('publish')->with(self::callback(
            static fn (Update $update): bool => $update->getId() === 'sensor-reading-1'
                && $update->isPrivate()
                && $update->getTopics() === ['tenant/room/1'],
        ));
        $outbox = $this->outbox();

        $outbox->queueMercure('tenant-1', 'sensor-reading-1', ['tenant/room/1'], ['value' => 25.5]);
        $outbox->queueMercure('tenant-1', 'sensor-reading-1', ['tenant/room/1'], ['value' => 25.5]);

        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM effect_outbox'));
        self::assertSame(['delivered' => 1, 'failed' => 0], $this->dispatcher(hub: $hub)->dispatchBatch());
    }

    public function testStripeReconciliationCanBeRetriedAfterFailure(): void
    {
        $stripe = $this->createMock(StripeService::class);
        $stripe->method('isEntityManagerOpen')->willReturn(true);
        $stripe->expects(self::exactly(2))->method('reconcilePaymentSuccess')
            ->with('organization-1')
            ->willReturnCallback(static function () use (&$attempt): void {
                $attempt = ($attempt ?? 0) + 1;
                if ($attempt === 1) {
                    throw new \RuntimeException('Stripe unavailable');
                }
            });
        $this->outbox()->queueStripeSync('tenant-1', 'stripe-payment-succeeded-1', 'organization-1');
        $dispatcher = $this->dispatcher(stripe: $stripe);

        self::assertSame(['delivered' => 0, 'failed' => 1], $dispatcher->dispatchBatch());
        $this->connection->executeStatement("UPDATE effect_outbox SET available_at = '2000-01-01 00:00:00+00:00'");
        self::assertSame(['delivered' => 1, 'failed' => 0], $dispatcher->dispatchBatch());
    }

    private function outbox(): TransactionalOutbox
    {
        return new TransactionalOutbox($this->connection);
    }

    private function dispatcher(
        ?MailerInterface $mailer = null,
        ?HubInterface $hub = null,
        ?StripeService $stripe = null,
    ): OutboxDispatcher {
        return new OutboxDispatcher(
            $this->connection,
            $mailer ?? $this->createMock(MailerInterface::class),
            $hub ?? $this->createMock(HubInterface::class),
            $stripe ?? $this->createMock(StripeService::class),
            $this->createMock(LoggerInterface::class),
        );
    }

    private function email(): Email
    {
        return (new Email())->from('noreply@test.local')->to('user@test.local')->subject('Status')->text('Updated');
    }
}
