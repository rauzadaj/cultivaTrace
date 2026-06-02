<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Farm;
use App\Entity\Organization;
use App\Entity\Plant;
use App\Entity\Room;
use App\Entity\Strain;
use App\Entity\User;
use App\Enum\LicenseStatus;
use App\Enum\SubscriptionPlan;
use App\Service\PlanLimitExceededException;
use App\Service\PlanLimitsService;
use App\Tests\Controller\ApiTestCase;

final class PlanLimitsServiceTest extends ApiTestCase
{
    private PlanLimitsService $service;

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
        ]);

        $this->service = new PlanLimitsService($this->entityManager);
    }

    // ── checkPlantLimit ────────────────────────────────────────────────────────

    public function testCheckPlantLimitDoesNotThrowWhenUnderLimit(): void
    {
        $org = $this->createOrganization('Org Under Limit');
        $org->setPlan(SubscriptionPlan::GROWTH); // max 500
        $this->entityManager->flush();

        $this->service->checkPlantLimit($org); // should not throw

        $this->addToAssertionCount(1);
    }

    public function testCheckPlantLimitThrowsWhenAtLimit(): void
    {
        $this->expectException(PlanLimitExceededException::class);

        $org  = $this->createOrganization('Org At Limit');
        $org->setPlan(SubscriptionPlan::GROWTH);
        $org->setLicenseStatus(LicenseStatus::ACTIVE);
        $user = $this->createUser($org, 'limit@test.local');
        $farm = $this->createFarm($org, 'Farm');
        $room = $this->createRoom($farm, 'Room');
        $strain = $this->createStrain($org, 'Strain');
        $this->entityManager->flush();

        // Inject exactly maxPlants plants
        $max = SubscriptionPlan::GROWTH->maxPlants(); // 500
        for ($i = 0; $i < $max; $i++) {
            $this->createPlant($room, $user, $strain, rfidTag: sprintf('RFID-%04d', $i));
        }
        $this->entityManager->flush();

        $this->service->checkPlantLimit($org);
    }

    public function testCheckPlantLimitExceptionCarriesMetadata(): void
    {
        $org  = $this->createOrganization('Org Meta');
        $org->setPlan(SubscriptionPlan::GROWTH);
        $org->setLicenseStatus(LicenseStatus::ACTIVE);
        $user   = $this->createUser($org, 'meta@test.local');
        $farm   = $this->createFarm($org, 'Farm Meta');
        $room   = $this->createRoom($farm, 'Room Meta');
        $strain = $this->createStrain($org, 'Strain Meta');

        $max = SubscriptionPlan::GROWTH->maxPlants();
        for ($i = 0; $i < $max; $i++) {
            $this->createPlant($room, $user, $strain, rfidTag: sprintf('META-%04d', $i));
        }
        $this->entityManager->flush();

        try {
            $this->service->checkPlantLimit($org);
            self::fail('PlanLimitExceededException expected');
        } catch (PlanLimitExceededException $e) {
            self::assertSame('plants', $e->limitType);
            self::assertSame($max, $e->current);
            self::assertSame($max, $e->max);
            self::assertSame(SubscriptionPlan::PRO, $e->upgradeTo);
        }
    }

    // ── checkFarmLimit ─────────────────────────────────────────────────────────

    public function testCheckFarmLimitDoesNotThrowWhenUnderLimit(): void
    {
        $org = $this->createOrganization('Org Farm OK');
        $org->setPlan(SubscriptionPlan::GROWTH); // max 1
        $this->entityManager->flush();

        $this->service->checkFarmLimit($org); // 0 farms < 1 max

        $this->addToAssertionCount(1);
    }

    public function testCheckFarmLimitThrowsWhenAtLimit(): void
    {
        $this->expectException(PlanLimitExceededException::class);

        $org = $this->createOrganization('Org Farm Full');
        $org->setPlan(SubscriptionPlan::GROWTH); // max 1
        $this->createFarm($org, 'Farm 1');
        $this->entityManager->flush();

        $this->service->checkFarmLimit($org);
    }

    public function testCheckFarmLimitProAllowsThreeSites(): void
    {
        $org = $this->createOrganization('Org Pro Farm');
        $org->setPlan(SubscriptionPlan::PRO); // max 3
        $this->createFarm($org, 'Farm 1');
        $this->createFarm($org, 'Farm 2');
        $this->entityManager->flush();

        $this->service->checkFarmLimit($org); // 2 < 3

        $this->addToAssertionCount(1);
    }

    // ── checkRoomLimit ─────────────────────────────────────────────────────────

    public function testCheckRoomLimitNeverThrowsBecauseRoomsAreUnlimited(): void
    {
        $org = $this->createOrganization('Org Rooms Unlimited');
        $org->setPlan(SubscriptionPlan::GROWTH);
        $farm = $this->createFarm($org, 'Farm');
        for ($i = 1; $i <= 20; $i++) {
            $this->createRoom($farm, "Room {$i}");
        }
        $this->entityManager->flush();

        $this->service->checkRoomLimit($org); // rooms are unlimited on all plans

        $this->addToAssertionCount(1);
    }

    // ── checkIoTAccess ─────────────────────────────────────────────────────────

    public function testCheckIoTAccessDoesNotThrowOnGrowthPlan(): void
    {
        $org = $this->createOrganization('Org Growth IoT');
        $org->setPlan(SubscriptionPlan::GROWTH);

        $this->service->checkIoTAccess($org);

        $this->addToAssertionCount(1);
    }

    public function testCheckIoTAccessDoesNotThrowOnProPlan(): void
    {
        $org = $this->createOrganization('Org Pro IoT');
        $org->setPlan(SubscriptionPlan::PRO);

        $this->service->checkIoTAccess($org);

        $this->addToAssertionCount(1);
    }

    // ── getLimits ──────────────────────────────────────────────────────────────

    public function testGetLimitsReturnsCorrectStructure(): void
    {
        $org  = $this->createOrganization('Org Limits');
        $org->setPlan(SubscriptionPlan::GROWTH);
        $farm = $this->createFarm($org, 'Farm');
        $this->createRoom($farm, 'Room');
        $this->entityManager->flush();

        $limits = $this->service->getLimits($org);

        self::assertArrayHasKey('plan', $limits);
        self::assertSame('growth', $limits['plan']);
        self::assertSame(1, $limits['farms']['current']);
        self::assertSame(1, $limits['farms']['max']);
        self::assertTrue($limits['iot']['available']);
    }
}
