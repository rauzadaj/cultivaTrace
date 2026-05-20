<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Farm;
use App\Entity\Organization;
use App\Entity\Plant;
use App\Entity\PlantEvent;
use App\Entity\Room;
use App\Entity\Strain;
use App\Entity\User;
use App\Enum\RoomType;
use App\Repository\PlantEventRepository;
use App\Service\HashChainService;
use App\Tests\Controller\ApiTestCase;
use Symfony\Component\Uid\Uuid;

final class HashChainServiceTest extends ApiTestCase
{
    private HashChainService $hashChain;

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

        $this->hashChain = $this->container->get(HashChainService::class);
    }

    public function testComputeHashIsDeterministic(): void
    {
        $event = new PlantEvent();
        $event->setEventType('note');
        $event->setPayload(['message' => 'Hello']);
        $event->setOccurredAt(new \DateTimeImmutable('2026-01-01 12:00:00'));

        $hash1 = $this->hashChain->computeHash($event, str_repeat('0', 64));
        $hash2 = $this->hashChain->computeHash($event, str_repeat('0', 64));

        self::assertSame($hash1, $hash2);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash1);
    }

    public function testComputeHashChangesWithDifferentPreviousHash(): void
    {
        $event = new PlantEvent();
        $event->setEventType('note');
        $event->setPayload(['message' => 'Test']);
        $event->setOccurredAt(new \DateTimeImmutable('2026-01-01 12:00:00'));

        $hash1 = $this->hashChain->computeHash($event, str_repeat('0', 64));
        $hash2 = $this->hashChain->computeHash($event, str_repeat('a', 64));

        self::assertNotSame($hash1, $hash2);
    }

    public function testVerifyReturnsValidForIntactChain(): void
    {
        [$user, $plant] = $this->createChainFixture();

        /** @var PlantEventRepository $repo */
        $repo = $this->entityManager->getRepository(PlantEvent::class);
        $repo->appendEvent($plant, 'note', $user, ['message' => 'Event 1'], 'First');
        $repo->appendEvent($plant, 'stage_change', $user, ['from' => 'germination', 'to' => 'vegetation']);

        $result = $this->hashChain->verify($plant->getId());

        self::assertTrue($result['valid']);
        self::assertNull($result['broken_at']);
        self::assertSame(2, $result['checked']);
    }

    public function testVerifyReturnsZeroCheckedForPlantWithNoEvents(): void
    {
        [, $plant] = $this->createChainFixture();

        $result = $this->hashChain->verify($plant->getId());

        self::assertTrue($result['valid']);
        self::assertSame(0, $result['checked']);
    }

    public function testVerifyDetectsTamperedPayload(): void
    {
        [$user, $plant] = $this->createChainFixture();

        /** @var PlantEventRepository $repo */
        $repo = $this->entityManager->getRepository(PlantEvent::class);
        $repo->appendEvent($plant, 'note', $user, ['message' => 'Legitimate note']);

        // Tamper: modify the payload directly in the DB bypassing the service
        $this->entityManager->getConnection()->executeStatement(
            "UPDATE plant_event SET payload = '{\"message\":\"TAMPERED\"}' WHERE event_type = 'note'"
        );
        $this->entityManager->clear();

        $result = $this->hashChain->verify($plant->getId());

        self::assertFalse($result['valid']);
        self::assertNotNull($result['broken_at']);
        self::assertSame(0, $result['checked']);
    }

    /** @return array{0: User, 1: Plant} */
    private function createChainFixture(): array
    {
        $org    = $this->createOrganization('Hash Chain Org');
        $user   = $this->createUser($org, 'chain@test.local');
        $farm   = $this->createFarm($org, 'Hash Farm');
        $room   = $this->createRoom($farm, 'Hash Room', RoomType::Veg);
        $strain = $this->createStrain($org, 'Hash Strain');
        $plant  = $this->createPlant($room, $user, $strain, rfidTag: 'HASH-001');

        $this->entityManager->flush();

        return [$user, $plant];
    }
}
