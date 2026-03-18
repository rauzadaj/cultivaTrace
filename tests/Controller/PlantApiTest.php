<?php

namespace App\Tests\Controller;

use App\Entity\Farm;
use App\Entity\HarvestRecord;
use App\Entity\Organization;
use App\Entity\Plant;
use App\Entity\PlantEvent;
use App\Entity\Room;
use App\Entity\Strain;
use App\Entity\User;
use App\Repository\PlantEventRepository;
use App\Service\HashChainService;
use Symfony\Component\HttpFoundation\Response;

final class PlantApiTest extends ApiTestCase
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
            HarvestRecord::class,
        ]);
    }

    public function testPostPlantsCreatesPlantForAuthenticatedTenant(): void
    {
        $organization = $this->createOrganization('Org A');
        $user = $this->createUser($organization, 'grower-a@test.local');
        $farm = $this->createFarm($organization, 'Farm A');
        $room = $this->createRoom($farm, 'Veg Room');
        $strain = $this->createStrain($organization, 'Banana OG');

        $this->entityManager->flush();
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', '/api/plants', [
            'room' => sprintf('/api/rooms/%s', $room->getId()),
            'strain' => sprintf('/api/strains/%s', $strain->getId()),
            'rfidTag' => 'PLANT-A-001',
            'germinatedAt' => '2026-03-01',
            'stage' => 'germination',
        ]);

        $this->assertStatusCode(Response::HTTP_CREATED);

        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('PLANT-A-001', $payload['rfidTag']);
        self::assertArrayNotHasKey('tenantId', $payload);

        /** @var Plant $plant */
        $plant = $this->entityManager->getRepository(Plant::class)->findOneBy(['rfidTag' => 'PLANT-A-001']);
        self::assertNotNull($plant);
        self::assertSame((string) $organization->getId(), (string) $plant->getTenantId());
        self::assertSame($user->getEmail(), $plant->getCreatedBy()->getEmail());
    }

    public function testGetPlantsReturnsOnlyPlantsOfAuthenticatedTenant(): void
    {
        $organizationA = $this->createOrganization('Org A');
        $userA = $this->createUser($organizationA, 'user-a@test.local');
        $farmA = $this->createFarm($organizationA, 'Farm A');
        $roomA = $this->createRoom($farmA, 'Room A');
        $strainA = $this->createStrain($organizationA, 'Strain A');
        $plantA = $this->createPlant($roomA, $userA, $strainA, rfidTag: 'PLANT-A');

        $organizationB = $this->createOrganization('Org B');
        $userB = $this->createUser($organizationB, 'user-b@test.local');
        $farmB = $this->createFarm($organizationB, 'Farm B');
        $roomB = $this->createRoom($farmB, 'Room B');
        $strainB = $this->createStrain($organizationB, 'Strain B');
        $plantB = $this->createPlant($roomB, $userB, $strainB, rfidTag: 'PLANT-B');

        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->authorizeClient($userA);
        $this->client->request('GET', '/api/plants');

        $this->assertStatusCode(Response::HTTP_OK);

        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        $members = $payload['member'] ?? $payload['hydra:member'] ?? [];

        self::assertCount(1, $members);
        self::assertSame('PLANT-A', $members[0]['rfidTag']);
        self::assertStringNotContainsString('PLANT-B', $this->client->getResponse()->getContent() ?: '');
        self::assertStringNotContainsString((string) $organizationA->getId(), $this->client->getResponse()->getContent() ?: '');
        self::assertStringNotContainsString((string) $organizationB->getId(), $this->client->getResponse()->getContent() ?: '');
    }

    public function testAppendEventProducesValidHashChain(): void
    {
        $organization = $this->createOrganization('Org A');
        $user = $this->createUser($organization, 'auditor@test.local');
        $farm = $this->createFarm($organization, 'Farm A');
        $room = $this->createRoom($farm, 'Room A');
        $strain = $this->createStrain($organization, 'Strain A');
        $plant = $this->createPlant($room, $user, $strain, rfidTag: 'PLANT-HASH');

        $this->entityManager->flush();

        /** @var PlantEventRepository $eventRepository */
        $eventRepository = $this->entityManager->getRepository(PlantEvent::class);
        $eventRepository->appendEvent($plant, 'note', $user, ['message' => 'Initial observation'], 'First note');
        $eventRepository->appendEvent($plant, 'stage_change', $user, ['from' => 'germination', 'to' => 'vegetation']);

        $hashChain = new HashChainService($this->container->get('doctrine'));
        $verification = $hashChain->verify($plant->getId());

        self::assertTrue($verification['valid']);
        self::assertSame(2, $verification['checked']);
        self::assertNull($verification['broken_at']);
    }
}
