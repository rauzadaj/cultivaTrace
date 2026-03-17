<?php

namespace App\Infrastructure\Persistence\Doctrine\EventSubscriber;

use App\Entity\Farm;
use App\Entity\User;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use InvalidArgumentException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class FarmTenantSubscriber implements EventSubscriber
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
        ];
    }

    public function prePersist(PrePersistEventArgs $event): void
    {
        $farm = $event->getObject();
        if (!$farm instanceof Farm) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        if (!$user instanceof User) {
            throw new InvalidArgumentException('An authenticated user is required to create a farm.');
        }

        $organization = $user->getOrganization();
        if ($organization === null) {
            throw new InvalidArgumentException('The authenticated user must belong to an organization.');
        }

        $farm->setOrganization($organization);
        $farm->setTenantId($organization->getId());
    }
}
