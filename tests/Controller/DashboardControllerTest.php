<?php

namespace App\Tests\Controller;

use App\Entity\Farm;
use App\Entity\HarvestRecord;
use App\Entity\Organization;
use App\Entity\Plant;
use App\Entity\Room;
use App\Entity\Sensor;
use App\Entity\Strain;
use App\Entity\User;
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
        $roomVeg = $this->createRoom($farm, 'Veg Room', 'veg');
        $roomFlower = $this->createRoom($farm, 'Flower Room', 'flower');
        $strain = $this->createStrain($organization, 'OG Kush');

        for ($index = 1; $index <= 10; $index++) {
            $room = $index <= 5 ? $roomVeg : $roomFlower;
            $this->createPlant($room, $user, $strain, rfidTag: sprintf('PLANT-%02d', $index));
        }

        $this->entityManager->flush();
        $this->authorizeClient($user);

        $this->client->request('GET', '/api/dashboard');

        $this->assertStatusCode(Response::HTTP_OK);

        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('pro', $payload['organization']['plan']);
        self::assertSame(10, $payload['limits']['plants']['current']);
        self::assertSame('pro', $payload['limits']['plan']);
        self::assertArrayHasKey('alerts', $payload);
        self::assertArrayHasKey('harvests', $payload);
        self::assertArrayHasKey('plants', $payload);
    }
}
