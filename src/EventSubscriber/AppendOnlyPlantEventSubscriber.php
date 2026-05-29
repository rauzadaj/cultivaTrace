<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\PlantEvent;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
final class AppendOnlyPlantEventSubscriber
{
    public function preUpdate(PreUpdateEventArgs $event): void
    {
        if ($event->getObject() instanceof PlantEvent) {
            throw new \LogicException('PlantEvent is append-only and cannot be modified or deleted.');
        }
    }

    public function preRemove(PreRemoveEventArgs $event): void
    {
        if ($event->getObject() instanceof PlantEvent) {
            throw new \LogicException('PlantEvent is append-only and cannot be modified or deleted.');
        }
    }
}
