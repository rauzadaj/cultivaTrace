<?php

namespace App\Infrastructure\Persistence\Doctrine\EventSubscriber;

use App\Domain\Cultivation\Exception\JournalEntryAppendOnlyViolation;
use App\Domain\Cultivation\Model\JournalEntry;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

final class JournalEntryAppendOnlySubscriber implements EventSubscriber
{
    public function getSubscribedEvents(): array
    {
        return [
            Events::preUpdate,
            Events::preRemove,
        ];
    }

    public function preUpdate(PreUpdateEventArgs $event): void
    {
        if ($event->getObject() instanceof JournalEntry) {
            throw JournalEntryAppendOnlyViolation::update();
        }
    }

    public function preRemove(PreRemoveEventArgs $event): void
    {
        if ($event->getObject() instanceof JournalEntry) {
            throw JournalEntryAppendOnlyViolation::delete();
        }
    }
}
