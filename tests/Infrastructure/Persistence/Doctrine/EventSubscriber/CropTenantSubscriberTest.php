<?php

namespace App\Tests\Infrastructure\Persistence\Doctrine\EventSubscriber;

use App\Domain\Cultivation\Model\Crop;
use App\Domain\Cultivation\Model\JournalEntry;
use App\Entity\Organization;
use App\Entity\User;
use App\Infrastructure\Persistence\Doctrine\EventSubscriber\CropTenantSubscriber;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class CropTenantSubscriberTest extends TestCase
{
    public function testSubscriberAssignsTenantToCropAndJournalEntryFromAuthenticatedUser(): void
    {
        $organization = (new Organization())
            ->setName('CultivaTrace Tenant')
            ->setCountry('FR');
        $user = (new User())
            ->setEmail('tenant@cultivatrace.local')
            ->setPassword('hashed-password')
            ->setRole('ROLE_ADMIN')
            ->setOrganization($organization);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $subscriber = new CropTenantSubscriber($tokenStorage);

        $crop = new Crop();
        $subscriber->prePersist($this->createPrePersistEvent($crop));

        self::assertSame((string) $organization->getId(), (string) $crop->getTenantId());

        $entry = (new JournalEntry())
            ->setCrop($crop);
        $subscriber->prePersist($this->createPrePersistEvent($entry));

        self::assertSame((string) $organization->getId(), (string) $entry->getTenantId());
    }

    public function testSubscriberRejectsCultivationCreationWithoutAuthenticatedUser(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $subscriber = new CropTenantSubscriber($tokenStorage);

        $this->expectException(InvalidArgumentException::class);

        $subscriber->prePersist($this->createPrePersistEvent(new Crop()));
    }

    private function createPrePersistEvent(object $object): PrePersistEventArgs
    {
        return new PrePersistEventArgs(
            $object,
            $this->createMock(EntityManagerInterface::class),
        );
    }
}
