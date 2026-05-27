<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\PlantEvent;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

final class AppendOnlyPlantEventSubscriber implements EventSubscriber
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
