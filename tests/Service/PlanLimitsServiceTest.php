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
        $org->setPlan(SubscriptionPlan::STARTER); // max 200
        $this->entityManager->flush();

        $this->service->checkPlantLimit($org); // should not throw

        $this->addToAssertionCount(1);
    }

    public function testCheckPlantLimitThrowsWhenAtLimit(): void
    {
        $this->expectException(PlanLimitExceededException::class);

        $org  = $this->createOrganization('Org At Limit');
        $org->setPlan(SubscriptionPlan::STARTER);
        $org->setLicenseStatus(LicenseStatus::ACTIVE);
        $user = $this->createUser($org, 'limit@test.local');
        $farm = $this->createFarm($org, 'Farm');
        $room = $this->createRoom($farm, 'Room');
        $strain = $this->createStrain($org, 'Strain');
        $this->entityManager->flush();

        // Inject exactly maxPlants active plants
        $max = SubscriptionPlan::STARTER->maxPlants(); // 200
        for ($i = 0; $i < $max; $i++) {
            $this->createPlant($room, $user, $strain, rfidTag: sprintf('RFID-%04d', $i));
        }
        $this->entityManager->flush();

        $this->service->checkPlantLimit($org);
    }

    public function testCheckPlantLimitExceptionCarriesMetadata(): void
    {
        $org  = $this->createOrganization('Org Meta');
        $org->setPlan(SubscriptionPlan::STARTER);
        $org->setLicenseStatus(LicenseStatus::ACTIVE);
        $user   = $this->createUser($org, 'meta@test.local');
        $farm   = $this->createFarm($org, 'Farm Meta');
        $room   = $this->createRoom($farm, 'Room Meta');
        $strain = $this->createStrain($org, 'Strain Meta');

        $max = SubscriptionPlan::STARTER->maxPlants();
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

    // ── checkRoomLimit ─────────────────────────────────────────────────────────

    public function testCheckRoomLimitDoesNotThrowWhenUnderLimit(): void
    {
        $org = $this->createOrganization('Org Rooms OK');
        $org->setPlan(SubscriptionPlan::STARTER); // max 2
        $farm = $this->createFarm($org, 'Farm');
        $this->createRoom($farm, 'Room 1');
        $this->entityManager->flush();

        $this->service->checkRoomLimit($org); // 1 room < 2 max

        $this->addToAssertionCount(1);
    }

    public function testCheckRoomLimitThrowsAtLimit(): void
    {
        $this->expectException(PlanLimitExceededException::class);

        $org  = $this->createOrganization('Org Rooms Full');
        $org->setPlan(SubscriptionPlan::STARTER); // max 2
        $farm = $this->createFarm($org, 'Farm');
        $this->createRoom($farm, 'Room 1');
        $this->createRoom($farm, 'Room 2');
        $this->entityManager->flush();

        $this->service->checkRoomLimit($org);
    }

    // ── checkIoTAccess ─────────────────────────────────────────────────────────

    public function testCheckIoTAccessThrowsOnStarterPlan(): void
    {
        $this->expectException(PlanLimitExceededException::class);
        $this->expectExceptionMessageMatches('/IoT/');

        $org = $this->createOrganization('Org Starter IoT');
        $org->setPlan(SubscriptionPlan::STARTER);

        $this->service->checkIoTAccess($org);
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
        $org->setPlan(SubscriptionPlan::STARTER);
        $farm = $this->createFarm($org, 'Farm');
        $this->createRoom($farm, 'Room');
        $this->entityManager->flush();

        $limits = $this->service->getLimits($org);

        self::assertArrayHasKey('plan', $limits);
        self::assertSame('starter', $limits['plan']);
        self::assertSame(1, $limits['rooms']['current']);
        self::assertSame(2, $limits['rooms']['max']);
        self::assertFalse($limits['iot']['available']);
    }
}
