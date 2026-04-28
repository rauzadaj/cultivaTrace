<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Alert;
use App\Entity\Farm;
use App\Entity\Organization;
use App\Entity\Room;
use App\Entity\Sensor;
use App\Entity\User;
use App\Enum\LicenseStatus;
use App\Enum\RoomType;
use App\Repository\SensorReadingRepository;
use Symfony\Component\HttpFoundation\Response;

final class SensorControllerTest extends ApiTestCase
{
    protected function setUp(): void
    {
        $_ENV['MERCURE_URL'] = 'http://mercure/.well-known/mercure';
        $_SERVER['MERCURE_URL'] = 'http://mercure/.well-known/mercure';
        $_ENV['MERCURE_PUBLIC_URL'] = 'http://localhost:9000/.well-known/mercure';
        $_SERVER['MERCURE_PUBLIC_URL'] = 'http://localhost:9000/.well-known/mercure';
        $_ENV['MERCURE_JWT_SECRET'] = '!ChangeThisMercureHubJWTSecretKey!';
        $_SERVER['MERCURE_JWT_SECRET'] = '!ChangeThisMercureHubJWTSecretKey!';
        $_ENV['MAILER_DSN'] = 'null://null';
        $_SERVER['MAILER_DSN'] = 'null://null';

        parent::setUp();

        $this->resetSchema([
            Organization::class,
            User::class,
            Farm::class,
            Room::class,
            Sensor::class,
            Alert::class,
        ]);

        $this->entityManager->getConnection()->executeStatement(
            'CREATE TABLE IF NOT EXISTS sensor_reading (
                sensor_id   UUID        NOT NULL,
                tenant_id   UUID        NOT NULL,
                value       FLOAT       NOT NULL,
                recorded_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )'
        );
        $this->entityManager->getConnection()->executeStatement(
            'CREATE INDEX IF NOT EXISTS idx_sensor_reading ON sensor_reading (sensor_id, recorded_at DESC)'
        );

    }

    public function testPostReadingStoresValueAndReturnsCreated(): void
    {
        [$user, $sensor] = $this->createSensorFixture();
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', sprintf('/api/sensors/%s/reading', $sensor->getId()), [
            'value' => 25.5,
        ]);

        $this->assertStatusCode(Response::HTTP_CREATED);

        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['stored']);
        self::assertFalse($payload['alerted']);

        $readingRepository = new SensorReadingRepository($this->entityManager->getConnection());
        $latest = $readingRepository->findLatest((string) $sensor->getId());

        self::assertNotNull($latest);
        self::assertSame(25.5, (float) $latest['value']);
    }

    public function testPostReadingPersistsAlertWhenThresholdIsExceeded(): void
    {
        [$user, $sensor] = $this->createSensorFixture('sensor-alert@test.local', 'temperature');
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', sprintf('/api/sensors/%s/reading', $sensor->getId()), [
            'value' => 40.0,
        ]);

        $this->assertStatusCode(Response::HTTP_CREATED);

        $alerts = $this->entityManager->getRepository(Alert::class)->findAll();
        self::assertCount(1, $alerts);
        self::assertSame('sensor_threshold', $alerts[0]->getType());
        self::assertSame((string) $sensor->getTenantId(), (string) $alerts[0]->getTenantId());
    }

    public function testPostReadingWithoutValueReturns422(): void
    {
        [$user, $sensor] = $this->createSensorFixture('sensor-missing@test.local', 'temperature');
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', sprintf('/api/sensors/%s/reading', $sensor->getId()), []);

        $this->assertStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString('value est obligatoire', $this->client->getResponse()->getContent() ?: '');
    }

    public function testPostReadingRejectsUserWithoutWriteRole(): void
    {
        [$organization, $sensor] = $this->createSensorEntityFixture('sensor-writer@test.local', 'temperature');
        $user = $this->createUser($organization, 'viewer@test.local', roles: []);
        $this->entityManager->flush();
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', sprintf('/api/sensors/%s/reading', $sensor->getId()), [
            'value' => 25.5,
        ]);

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);
    }

    public function testGetReadingsReturnsHistoryData(): void
    {
        [$user, $sensor] = $this->createSensorFixture('sensor-history@test.local', 'humidity');
        $this->authorizeClient($user);

        $readingRepository = new SensorReadingRepository($this->entityManager->getConnection());
        $readingRepository->insert((string) $sensor->getId(), (string) $sensor->getTenantId(), 52.5);
        $readingRepository->insert((string) $sensor->getId(), (string) $sensor->getTenantId(), 57.0);

        $this->client->request('GET', sprintf('/api/sensors/%s/readings?period=30d', $sensor->getId()));

        $this->assertStatusCode(Response::HTTP_OK);

        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame((string) $sensor->getId(), $payload['sensorId']);
        self::assertSame('30d', $payload['period']);
        self::assertIsArray($payload['data']);
        self::assertNotEmpty($payload['data']);
    }

    public function testGetReadingsRejectsUserWithoutOperatorPrivileges(): void
    {
        [$organization, $sensor] = $this->createSensorEntityFixture('sensor-reader@test.local', 'humidity');
        $user = $this->createUser($organization, 'viewer@test.local', roles: []);
        $this->entityManager->flush();
        $this->authorizeClient($user);

        $this->client->request('GET', sprintf('/api/sensors/%s/readings?period=30d', $sensor->getId()));

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);
    }

    /**
     * @return array{0: User, 1: Sensor}
     */
    private function createSensorFixture(string $email = 'sensor@test.local', string $type = 'temperature'): array
    {
        [$organization, $sensor] = $this->createSensorEntityFixture($email, $type);
        $user = $this->createUser($organization, $email);
        $this->entityManager->flush();

        return [$user, $sensor];
    }

    /**
     * @return array{0: Organization, 1: Sensor}
     */
    private function createSensorEntityFixture(string $email, string $type): array
    {
        $organization = $this->createOrganization('Org Sensors');
        $organization->setLicenseStatus(LicenseStatus::ACTIVE);
        $farm = $this->createFarm($organization, 'Farm Sensors');
        $room = $this->createRoom($farm, 'Room Sensors', RoomType::Veg);

        $sensor = new Sensor();
        $sensor->setTenantId($organization->getId());
        $sensor->setRoom($room);
        $sensor->setType($type);
        $sensor->setDeviceId(sprintf('TEST-%s', strtoupper($type)));
        $sensor->setProtocol('simulated');
        $sensor->setStatus('online');
        $sensor->setLastSeen(new \DateTimeImmutable());
        $sensor->setThresholds([
            'min' => 18.0,
            'max' => 28.0,
            'unit' => $type === 'temperature' ? '°C' : '%',
            'alertCooldownMinutes' => 60,
        ]);

        $this->entityManager->persist($sensor);

        return [$organization, $sensor];
    }
}
