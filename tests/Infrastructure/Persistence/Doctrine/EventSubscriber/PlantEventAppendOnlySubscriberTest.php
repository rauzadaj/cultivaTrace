<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\Doctrine\EventSubscriber;

use App\Entity\Farm;
use App\Entity\Organization;
use App\Entity\Plant;
use App\Entity\PlantEvent;
use App\Entity\Room;
use App\Entity\Strain;
use App\Entity\User;
use App\Enum\PlantStage;
use App\Enum\PlantStatus;
use App\Enum\RoomType;
use App\Repository\PlantEventRepository;
use App\Tests\Controller\ApiTestCase;

final class PlantEventAppendOnlySubscriberTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetSchema([
            Organization::class,
            User::class,
            Farm::class,
            Room::class,
            Strain::class,
            Plant::class,
            PlantEvent::class,
        ]);
    }

    public function testOrmUpdateIsRejected(): void
    {
        [$event] = $this->createEventFixture();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('PlantEvent is append-only and cannot be modified or deleted.');

        $reflection = new \ReflectionObject($event);
        $notesProperty = $reflection->getProperty('notes');
        $notesProperty->setAccessible(true);
        $notesProperty->setValue($event, 'tamper attempt');

        $this->entityManager->flush();
    }

    public function testOrmDeleteIsRejected(): void
    {
        [$event] = $this->createEventFixture();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('PlantEvent is append-only and cannot be modified or deleted.');

        $this->entityManager->remove($event);
        $this->entityManager->flush();
    }

    /**
     * @return array{0: PlantEvent, 1: Plant}
     */
    private function createEventFixture(): array
    {
        $organization = $this->createOrganization('Append-only Org');
        $user = $this->createUser($organization, 'append-only@test.local');
        $farm = $this->createFarm($organization, 'Append-only Farm');
        $room = $this->createRoom($farm, 'Append-only Room', RoomType::Veg);
        $strain = $this->createStrain($organization, 'Append-only Strain');
        $plant = $this->createPlant($room, $user, $strain, PlantStage::GERMINATION, PlantStatus::ACTIVE, 'RFID-APPEND');

        $this->entityManager->flush();

        /** @var PlantEventRepository $eventRepository */
        $eventRepository = $this->entityManager->getRepository(PlantEvent::class);
        $event = $eventRepository->appendEvent($plant, 'note', $user, notes: 'created');

        $this->entityManager->clear();

        /** @var PlantEvent $managedEvent */
        $managedEvent = $this->entityManager->find(PlantEvent::class, $event->getId());

        return [$managedEvent, $plant];
    }
}
