<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Alert;
use App\Entity\DestructionIntent;
use App\Entity\Farm;
use App\Entity\HarvestRecord;
use App\Entity\InputRecord;
use App\Entity\Organization;
use App\Entity\Plant;
use App\Entity\PlantEvent;
use App\Entity\Room;
use App\Entity\Sensor;
use App\Entity\Strain;
use App\Entity\User;
use App\Enum\LicenseStatus;
use App\Enum\RoomType;
use Symfony\Component\HttpFoundation\Response;

/**
 * Covers the read-only ROLE_VIEWER persona (accountant / inspector / investor):
 * viewers may consult operational data but must never mutate it.
 */
final class ViewerRoleAccessTest extends ApiTestCase
{
    private Organization $organization;
    private User $viewer;
    private Plant $plant;
    private Sensor $sensor;
    private Alert $alert;

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
            InputRecord::class,
            HarvestRecord::class,
            DestructionIntent::class,
            Sensor::class,
            Alert::class,
        ]);

        $this->organization = $this->createOrganization('Org Viewer');
        $this->organization->setLicenseStatus(LicenseStatus::ACTIVE);
        $this->viewer = $this->createUser($this->organization, 'viewer@test.local', role: 'ROLE_VIEWER');
        $owner = $this->createUser($this->organization, 'owner@test.local', role: 'ROLE_ORG_ADMIN');

        $farm = $this->createFarm($this->organization, 'Viewer Farm');
        $room = $this->createRoom($farm, 'Viewer Room', RoomType::Flower);
        $strain = $this->createStrain($this->organization, 'Viewer Strain');
        $this->plant = $this->createPlant($room, $owner, $strain, rfidTag: 'VIEWER-PLANT');

        $this->sensor = new Sensor();
        $this->sensor->setTenantId($this->organization->getId());
        $this->sensor->setRoom($room);
        $this->sensor->setType('temperature');
        $this->sensor->setDeviceId('VIEWER-SENSOR');
        $this->sensor->setProtocol('simulated');
        $this->sensor->setStatus('online');
        $this->sensor->setLastSeen(new \DateTimeImmutable());
        $this->entityManager->persist($this->sensor);

        $this->alert = (new Alert())
            ->setTenantId($this->organization->getId())
            ->setType('sensor_threshold')
            ->setSeverity('warning')
            ->setTitle('Viewer Alert')
            ->setMessage('Threshold exceeded');
        $this->entityManager->persist($this->alert);

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function readableEndpointProvider(): iterable
    {
        yield 'farms collection' => ['/api/farms'];
        yield 'rooms collection' => ['/api/rooms'];
        yield 'strains collection' => ['/api/strains'];
        yield 'plants collection' => ['/api/plants'];
        yield 'sensors collection' => ['/api/sensors'];
        yield 'alerts collection' => ['/api/alerts'];
        yield 'dashboard' => ['/api/dashboard'];
    }

    /**
     * @dataProvider readableEndpointProvider
     */
    public function testViewerCanReadOperationalEndpoints(string $uri): void
    {
        $this->authorizeClient($this->viewer);

        $this->client->request('GET', $uri);

        $this->assertStatusCode(Response::HTTP_OK);
    }

    public function testViewerCanReadSensorHistory(): void
    {
        $this->authorizeClient($this->viewer);

        $this->client->request('GET', sprintf('/api/sensors/%s/readings?period=30d', $this->sensor->getId()));

        $this->assertStatusCode(Response::HTTP_OK);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    public static function forbiddenWriteProvider(): iterable
    {
        yield 'create farm' => ['POST', '/api/farms', ['name' => 'Nope']];
        yield 'create room' => ['POST', '/api/rooms', ['name' => 'Nope']];
        yield 'create strain' => ['POST', '/api/strains', ['name' => 'Nope']];
        yield 'create plant' => ['POST', '/api/plants', ['rfidTag' => 'NOPE']];
    }

    /**
     * @dataProvider forbiddenWriteProvider
     *
     * @param array<string, mixed> $payload
     */
    public function testViewerCannotMutateViaApiPlatform(string $method, string $uri, array $payload): void
    {
        $this->authorizeClient($this->viewer);

        $this->apiJsonRequest($method, $uri, $payload);

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);
    }

    public function testViewerCannotPatchPlant(): void
    {
        $this->authorizeClient($this->viewer);

        $this->client->request(
            'PATCH',
            sprintf('/api/plants/%s', $this->plant->getId()),
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/merge-patch+json',
                'HTTP_ACCEPT' => 'application/ld+json',
            ],
            json_encode(['rfidTag' => 'HACKED'], JSON_THROW_ON_ERROR),
        );

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);
    }

    public function testViewerCannotPostSensorReading(): void
    {
        $this->authorizeClient($this->viewer);

        $this->apiJsonRequest('POST', sprintf('/api/sensors/%s/reading', $this->sensor->getId()), [
            'value' => 22.5,
        ]);

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);
    }

    public function testViewerCannotAcknowledgeAlert(): void
    {
        $this->authorizeClient($this->viewer);

        $this->apiJsonRequest('POST', sprintf('/api/alerts/%s/acknowledge', $this->alert->getId()), []);

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);
    }

    public function testAdminCanInviteViewerRole(): void
    {
        /** @var Organization $organization */
        $organization = $this->entityManager->find(Organization::class, $this->organization->getId());
        $admin = $this->createUser($organization, 'admin-invite@test.local', role: 'ROLE_ORG_ADMIN');
        $this->entityManager->flush();
        $this->authorizeClient($admin);

        $this->apiJsonRequest('POST', '/api/organization/invitations', [
            'email' => 'new-viewer@test.local',
            'role' => 'ROLE_VIEWER',
        ]);

        $this->assertStatusCode(Response::HTTP_CREATED);
    }
}
