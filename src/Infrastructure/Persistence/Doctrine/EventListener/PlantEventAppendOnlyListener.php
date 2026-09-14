<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\EventListener;

use App\Entity\PlantEvent;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/** Complements the PostgreSQL trigger; raw SQL must still be protected by migrations. */
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
final class PlantEventAppendOnlyListener
{
    public function preUpdate(PreUpdateEventArgs $event): void
    {
        if ($event->getObject() instanceof PlantEvent) {
            throw new \LogicException('PlantEvent is append-only; updates are forbidden. Append a corrective event instead.');
        }
    }

    public function preRemove(PreRemoveEventArgs $event): void
    {
        if ($event->getObject() instanceof PlantEvent) {
            throw new \LogicException('PlantEvent is append-only; deletes are forbidden.');
        }
    }
}
