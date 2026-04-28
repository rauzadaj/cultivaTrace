<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Farm;
use App\Entity\HarvestRecord;
use App\Entity\Organization;
use App\Entity\Plant;
use App\Entity\PlantEvent;
use App\Entity\Room;
use App\Entity\Sensor;
use App\Entity\Strain;
use App\Entity\User;
use App\Enum\RoomType;
use App\Enum\SubscriptionPlan;
use Symfony\Component\HttpFoundation\Response;

final class DashboardControllerTest extends ApiTestCase
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
            Sensor::class,
        ]);
    }

    public function testDashboardReturnsPlanAndLimits(): void
    {
        $organization = $this->createOrganization('Org Dashboard');
        $organization->setPlan(SubscriptionPlan::PRO);

        $user = $this->createUser($organization, 'dashboard@test.local');
        $farm = $this->createFarm($organization, 'Farm Dashboard');
        $roomVeg = $this->createRoom($farm, 'Veg Room', RoomType::Veg);
        $roomFlower = $this->createRoom($farm, 'Flower Room', RoomType::Flower);
        $strain = $this->createStrain($organization, 'OG Kush');

        for ($index = 1; $index <= 10; $index++) {
            $room = $index <= 5 ? $roomVeg : $roomFlower;
            $this->createPlant($room, $user, $strain, rfidTag: sprintf('PLANT-%02d', $index));
        }

        $plant = $this->createPlant($roomVeg, $user, $strain, rfidTag: 'PLANT-EVENT');
        $event = (new PlantEvent())
            ->setTenantId($organization->getId())
            ->setPlant($plant)
            ->setUser($user)
            ->setEventType('note')
            ->setNotes('Observation recente')
            ->setOccurredAt(new \DateTimeImmutable())
            ->setHashSelf(str_repeat('a', 64))
            ->setIpAddress('127.0.0.1');
        $this->entityManager->persist($event);

        $this->entityManager->flush();
        $this->authorizeClient($user);

        $this->client->request('GET', '/api/dashboard');

        $this->assertStatusCode(Response::HTTP_OK);

        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('pro', $payload['organization']['plan']);
        self::assertSame(11, $payload['limits']['plants']['current']);
        self::assertSame('pro', $payload['limits']['plan']);
        self::assertArrayHasKey('alerts', $payload);
        self::assertArrayHasKey('harvests', $payload);
        self::assertArrayHasKey('plants', $payload);
        self::assertArrayHasKey('rooms', $payload);
        self::assertArrayHasKey('overview', $payload);
        self::assertCount(3, $payload['overview']['spotlightPlants']);
        self::assertCount(1, $payload['overview']['recentEvents']);
        self::assertSame('Observation recente', $payload['overview']['recentEvents'][0]['notes']);
    }

    public function testDashboardRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/dashboard');

        $this->assertStatusCode(Response::HTTP_UNAUTHORIZED);
    }
}
