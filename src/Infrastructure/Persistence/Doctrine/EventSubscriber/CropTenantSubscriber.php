<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\EventSubscriber;

use App\Domain\Cultivation\Model\Crop;
use App\Domain\Cultivation\Model\JournalEntry;
use App\Entity\User;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use InvalidArgumentException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class CropTenantSubscriber implements EventSubscriber
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
        $object = $event->getObject();

        if ($object instanceof Crop) {
            $this->assignCropTenant($object);

            return;
        }

        if ($object instanceof JournalEntry) {
            $this->assignJournalEntryTenant($object);
        }
    }

    private function assignCropTenant(Crop $crop): void
    {
        if (null !== $crop->getTenantId()) {
            return;
        }

        $crop->setTenantId($this->resolveAuthenticatedTenantId());
    }

    private function assignJournalEntryTenant(JournalEntry $journalEntry): void
    {
        if (null !== $journalEntry->getTenantId()) {
            return;
        }

        $crop = $journalEntry->getCrop();
        if ($crop instanceof Crop && null !== $crop->getTenantId()) {
            $journalEntry->setTenantId($crop->getTenantId());

            return;
        }

        $journalEntry->setTenantId($this->resolveAuthenticatedTenantId());
    }

    private function resolveAuthenticatedTenantId(): \Symfony\Component\Uid\Uuid
    {
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        if (!$user instanceof User) {
            throw new InvalidArgumentException('An authenticated user is required to create cultivation journal data.');
        }

        $organization = $user->getOrganization();
        if (null === $organization) {
            throw new InvalidArgumentException('The authenticated user must belong to an organization.');
        }

        return $organization->getId();
    }
}
