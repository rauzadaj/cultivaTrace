<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Farm;
use App\Entity\HarvestRecord;
use App\Entity\Organization;
use App\Entity\Plant;
use App\Entity\PlantEvent;
use App\Entity\Room;
use App\Entity\Strain;
use App\Entity\User;
use App\Enum\LicenseStatus;
use App\Enum\SubscriptionPlan;
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
        $organization->setLicenseStatus(LicenseStatus::ACTIVE);
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


    public function testPostPlantsReturnsForbiddenWhenTenantLicenseIsPending(): void
    {
        $organization = $this->createOrganization('Org Pending');
        $organization->setLicenseStatus(LicenseStatus::PENDING);
        $user = $this->createUser($organization, 'pending@test.local');
        $farm = $this->createFarm($organization, 'Farm Pending');
        $room = $this->createRoom($farm, 'Pending Room');
        $strain = $this->createStrain($organization, 'Pending OG');

        $this->entityManager->flush();
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', '/api/plants', [
            'room' => sprintf('/api/rooms/%s', $room->getId()),
            'strain' => sprintf('/api/strains/%s', $strain->getId()),
            'rfidTag' => 'PLANT-PENDING-001',
            'germinatedAt' => '2026-03-01',
            'stage' => 'germination',
        ]);

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);

        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Tenant license status "pending" does not allow write operations.', $payload['detail']);
    }

    public function testGetPlantsIsNotBlockedWhenTenantLicenseIsPending(): void
    {
        $organization = $this->createOrganization('Org Read Pending');
        $organization->setLicenseStatus(LicenseStatus::PENDING);
        $user = $this->createUser($organization, 'pending-read@test.local');
        $farm = $this->createFarm($organization, 'Farm Read Pending');
        $room = $this->createRoom($farm, 'Room Read Pending');
        $strain = $this->createStrain($organization, 'Pending Read OG');
        $this->createPlant($room, $user, $strain, rfidTag: 'PLANT-PENDING-READ');

        $this->entityManager->flush();
        $this->authorizeClient($user);

        $this->client->request('GET', '/api/plants');

        $this->assertStatusCode(Response::HTTP_OK);
    }

    public function testTenantACannotSeeTenantBPlants(): void
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

    public function testUnauthenticatedRequestCannotAccessPlantCollection(): void
    {
        $this->client->request('GET', '/api/plants');

        $this->assertStatusCode(Response::HTTP_UNAUTHORIZED);
    }

    public function testPostPlantsRejectsUserWithoutPlantCreatePermission(): void
    {
        $organization = $this->createOrganization('Org Read Only');
        $organization->setLicenseStatus(LicenseStatus::ACTIVE);
        $user = $this->createUser($organization, 'readonly@test.local', roles: []);
        $farm = $this->createFarm($organization, 'Farm Read Only');
        $room = $this->createRoom($farm, 'Veg Room');
        $strain = $this->createStrain($organization, 'Banana OG');

        $this->entityManager->flush();
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', '/api/plants', [
            'room' => sprintf('/api/rooms/%s', $room->getId()),
            'strain' => sprintf('/api/strains/%s', $strain->getId()),
            'rfidTag' => 'PLANT-READONLY-001',
            'germinatedAt' => '2026-03-01',
            'stage' => 'germination',
        ]);

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);
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

    /**
     * PENDING and REJECTED pass the UserChecker (not suspended) but are blocked
     * by LicenseGuard in the processor → HTTP 403.
     *
     * @dataProvider licenseStatusBlockedByProcessorProvider
     */
    public function testPostPlantsReturnsForbiddenForPendingOrRejectedLicense(LicenseStatus $status): void
    {
        $organization = $this->createOrganization('Org ' . $status->value);
        $organization->setLicenseStatus($status);
        $user = $this->createUser($organization, $status->value . '@test.local');
        $farm = $this->createFarm($organization, 'Farm');
        $room = $this->createRoom($farm, 'Room');
        $strain = $this->createStrain($organization, 'Strain');

        $this->entityManager->flush();
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', '/api/plants', [
            'room'         => sprintf('/api/rooms/%s', $room->getId()),
            'strain'       => sprintf('/api/strains/%s', $strain->getId()),
            'rfidTag'      => 'PLANT-' . strtoupper($status->value) . '-001',
            'germinatedAt' => '2026-03-01',
            'stage'        => 'germination',
        ]);

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);

        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString($status->value, $payload['detail']);
    }

    /**
     * SUSPENDED and EXPIRED are caught by UserChecker at the authentication
     * layer (isSuspended() returns true for both) → HTTP 401.
     *
     * @dataProvider licenseStatusBlockedByUserCheckerProvider
     */
    public function testPostPlantsReturns401ForSuspendedOrExpiredOrganization(LicenseStatus $status): void
    {
        $organization = $this->createOrganization('Org ' . $status->value);
        $organization->setLicenseStatus($status);
        $user = $this->createUser($organization, $status->value . '-blocked@test.local');
        $farm = $this->createFarm($organization, 'Farm');
        $room = $this->createRoom($farm, 'Room');
        $strain = $this->createStrain($organization, 'Strain');

        $this->entityManager->flush();
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', '/api/plants', [
            'room'         => sprintf('/api/rooms/%s', $room->getId()),
            'strain'       => sprintf('/api/strains/%s', $strain->getId()),
            'rfidTag'      => 'PLANT-' . strtoupper($status->value) . '-BLOCKED',
            'germinatedAt' => '2026-03-01',
            'stage'        => 'germination',
        ]);

        $this->assertStatusCode(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return array<string, array{LicenseStatus}>
     */
    public static function licenseStatusBlockedByProcessorProvider(): array
    {
        return [
            'pending'  => [LicenseStatus::PENDING],
            'rejected' => [LicenseStatus::REJECTED],
        ];
    }

    /**
     * @return array<string, array{LicenseStatus}>
     */
    public static function licenseStatusBlockedByUserCheckerProvider(): array
    {
        return [
            'expired'   => [LicenseStatus::EXPIRED],
            'suspended' => [LicenseStatus::SUSPENDED],
        ];
    }

    public function testPlantFromAnotherTenantIsNotVisible(): void
    {
        $orgA = $this->createOrganization('Org A Cross-Tenant');
        $orgA->setLicenseStatus(LicenseStatus::ACTIVE);
        $userA = $this->createUser($orgA, 'owner-a@cross.test', role: 'ROLE_ORG_ADMIN');
        $farmA = $this->createFarm($orgA, 'Farm A');
        $roomA = $this->createRoom($farmA, 'Room A');
        $strainA = $this->createStrain($orgA, 'Strain A');
        $plantA = $this->createPlant($roomA, $userA, $strainA, rfidTag: 'CROSS-PLANT-A');

        $orgB = $this->createOrganization('Org B Cross-Tenant');
        $orgB->setLicenseStatus(LicenseStatus::ACTIVE);
        $userB = $this->createUser($orgB, 'owner-b@cross.test', role: 'ROLE_ORG_ADMIN');

        $this->entityManager->flush();

        // User from org B tries to GET a plant that belongs to org A.
        // The Doctrine tenant filter scopes queries to org B, so the plant is invisible → 404.
        $this->authorizeClient($userB);
        $this->client->request(
            'GET',
            sprintf('/api/plants/%s', $plantA->getId()),
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertStatusCode(Response::HTTP_NOT_FOUND);
    }

    public function testPostPlantsReturns402WhenPlanLimitIsExceeded(): void
    {
        $organization = $this->createOrganization('Org At Limit');
        $organization->setLicenseStatus(LicenseStatus::ACTIVE);
        $organization->setPlan(SubscriptionPlan::STARTER);
        $user = $this->createUser($organization, 'limit@test.local');
        $farm = $this->createFarm($organization, 'Farm');
        $room = $this->createRoom($farm, 'Room');
        $strain = $this->createStrain($organization, 'Strain');

        $max = SubscriptionPlan::STARTER->maxPlants();
        for ($i = 0; $i < $max; $i++) {
            $this->createPlant($room, $user, $strain, rfidTag: sprintf('RFID-LIMIT-%04d', $i));
        }
        $this->entityManager->flush();
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', '/api/plants', [
            'room'         => sprintf('/api/rooms/%s', $room->getId()),
            'strain'       => sprintf('/api/strains/%s', $strain->getId()),
            'rfidTag'      => 'RFID-OVER-LIMIT',
            'germinatedAt' => '2026-03-01',
            'stage'        => 'germination',
        ]);

        $this->assertStatusCode(402);

        $body = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('planLimit', $body);
        self::assertSame('plants', $body['planLimit']['limitType']);
        self::assertSame($max, $body['planLimit']['current']);
    }
}
