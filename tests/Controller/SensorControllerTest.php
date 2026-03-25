<?php

namespace App\Tests\Controller;

use App\Entity\Farm;
use App\Entity\Organization;
use App\Entity\Room;
use App\Entity\Sensor;
use App\Entity\User;
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

    public function testPostReadingWithoutValueReturns422(): void
    {
        [$user, $sensor] = $this->createSensorFixture('sensor-missing@test.local', 'temperature');
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', sprintf('/api/sensors/%s/reading', $sensor->getId()), []);

        $this->assertStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString('value est obligatoire', $this->client->getResponse()->getContent() ?: '');
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

    /**
     * @return array{0: User, 1: Sensor}
     */
    private function createSensorFixture(string $email = 'sensor@test.local', string $type = 'temperature'): array
    {
        $organization = $this->createOrganization('Org Sensors');
        $user = $this->createUser($organization, $email);
        $farm = $this->createFarm($organization, 'Farm Sensors');
        $room = $this->createRoom($farm, 'Room Sensors', 'veg');

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
        $this->entityManager->flush();

        return [$user, $sensor];
    }
}
