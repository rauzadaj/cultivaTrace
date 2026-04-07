<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\Doctrine\EventSubscriber;

use App\Domain\Cultivation\Exception\JournalEntryAppendOnlyViolation;
use App\Domain\Cultivation\Model\JournalEntry;
use App\Infrastructure\Persistence\Doctrine\EventSubscriber\JournalEntryAppendOnlySubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\TestCase;

final class JournalEntryAppendOnlySubscriberTest extends TestCase
{
    public function testSubscriberRejectsJournalEntryUpdate(): void
    {
        $subscriber = new JournalEntryAppendOnlySubscriber();
        $changeSet = ['notes' => ['before', 'after']];
        $event = new PreUpdateEventArgs(
            new JournalEntry(),
            $this->createMock(EntityManagerInterface::class),
            $changeSet,
        );

        $this->expectException(JournalEntryAppendOnlyViolation::class);

        $subscriber->preUpdate($event);
    }

    public function testSubscriberRejectsJournalEntryDelete(): void
    {
        $subscriber = new JournalEntryAppendOnlySubscriber();
        $event = new PreRemoveEventArgs(
            new JournalEntry(),
            $this->createMock(EntityManagerInterface::class),
        );

        $this->expectException(JournalEntryAppendOnlyViolation::class);

        $subscriber->preRemove($event);
    }
}
