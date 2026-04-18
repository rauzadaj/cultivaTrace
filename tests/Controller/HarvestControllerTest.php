<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\DestructionIntent;
use App\Entity\Farm;
use App\Entity\HarvestRecord;
use App\Entity\Organization;
use App\Entity\Plant;
use App\Entity\PlantEvent;
use App\Entity\Room;
use App\Entity\Strain;
use App\Entity\User;
use App\Enum\LicenseStatus;
use App\Enum\PlantStage;
use App\Enum\PlantStatus;
use Symfony\Component\HttpFoundation\Response;

final class HarvestControllerTest extends ApiTestCase
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
            DestructionIntent::class,
        ]);
    }

    public function testHarvestCreatesRecordUpdatesPlantAndAppendsEvent(): void
    {
        [$user, $plant] = $this->createHarvestFixture();
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', sprintf('/api/plants/%s/harvest', $plant->getId()), [
            'grossWeightG' => 120.50,
            'netWeightG' => 95.00,
            'harvestedAt' => '2026-03-18',
            'notes' => 'Harvest batch A',
        ]);

        $this->assertStatusCode(Response::HTTP_CREATED);

        /** @var HarvestRecord|null $harvest */
        $harvest = $this->entityManager->getRepository(HarvestRecord::class)->findOneBy(['plant' => $plant]);
        self::assertNotNull($harvest);
        self::assertSame(120.5, (float) $harvest->getGrossWeightG());
        self::assertSame(95.0, (float) $harvest->getNetWeightG());

        /** @var Plant $updatedPlant */
        $updatedPlant = $this->entityManager->find(Plant::class, $plant->getId());
        self::assertSame(PlantStatus::HARVESTED, $updatedPlant->getStatus());
        self::assertSame(PlantStage::HARVEST, $updatedPlant->getStage());

        /** @var list<PlantEvent> $events */
        $events = $this->entityManager->getRepository(PlantEvent::class)->findBy(['plant' => $plant], ['occurredAt' => 'ASC']);
        self::assertCount(1, $events);
        self::assertSame('harvest', $events[0]->getEventType());
    }

    public function testHarvestRejectsNetWeightGreaterThanGrossWeight(): void
    {
        [$user, $plant] = $this->createHarvestFixture('PLANT-HARVEST-INVALID');
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', sprintf('/api/plants/%s/harvest', $plant->getId()), [
            'grossWeightG' => 100,
            'netWeightG' => 110,
        ]);

        $this->assertStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString('poids net', $this->client->getResponse()->getContent() ?: '');
    }

    public function testHarvestReturnsForbiddenWhenTenantLicenseIsPending(): void
    {
        [$user, $plant] = $this->createHarvestFixture('PLANT-HARVEST-PENDING');
        $user->getOrganization()->setLicenseStatus(LicenseStatus::PENDING);
        $this->entityManager->flush();
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', sprintf('/api/plants/%s/harvest', $plant->getId()), [
            'grossWeightG' => 120.50,
            'netWeightG' => 95.00,
            'harvestedAt' => '2026-03-18',
        ]);

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);
        self::assertStringContainsString('status "pending"', $this->client->getResponse()->getContent() ?: '');
    }

    public function testDestroyCreatesIntentAndReturnsLegalWindow(): void
    {
        [$user, $plant] = $this->createHarvestFixture('PLANT-DESTROY', 'ROLE_ORG_ADMIN');
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', sprintf('/api/plants/%s/destroy', $plant->getId()), [
            'reason' => 'Mold contamination',
        ]);

        $this->assertStatusCode(Response::HTTP_CREATED);

        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('legalDateMin', $payload);
        self::assertArrayHasKey('daysRemaining', $payload);
        self::assertGreaterThanOrEqual(6, $payload['daysRemaining']);

        /** @var DestructionIntent|null $intent */
        $intent = $this->entityManager->getRepository(DestructionIntent::class)->find($payload['id']);
        self::assertNotNull($intent);
        self::assertSame('pending', $intent->getStatus());
    }

    public function testDestroyReturnsForbiddenWhenTenantLicenseIsPending(): void
    {
        [$user, $plant] = $this->createHarvestFixture('PLANT-DESTROY-PENDING', 'ROLE_ORG_ADMIN');
        $user->getOrganization()->setLicenseStatus(LicenseStatus::PENDING);
        $this->entityManager->flush();
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', sprintf('/api/plants/%s/destroy', $plant->getId()), [
            'reason' => 'Mold contamination',
        ]);

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);
        self::assertStringContainsString('status "pending"', $this->client->getResponse()->getContent() ?: '');
    }

    public function testConfirmDestroyRejectsBeforeLegalDelay(): void
    {
        [$user, $intent] = $this->createDestructionIntentFixture(role: 'ROLE_ORG_ADMIN');
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', sprintf('/api/destructions/%s/confirm', $intent->getId()), [
            'totalWeightG' => 100,
            'nonCannabisRatio' => 0.60,
            'photoUrls' => ['https://example.test/photo.jpg'],
        ]);

        $this->assertStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString('Destruction impossible avant le', $this->client->getResponse()->getContent() ?: '');
    }

    public function testConfirmDestroyReturnsForbiddenWhenTenantLicenseIsPending(): void
    {
        [$user, $intent] = $this->createDestructionIntentFixture('-8 days', 'ROLE_ORG_ADMIN');
        $user->getOrganization()->setLicenseStatus(LicenseStatus::PENDING);
        $this->entityManager->flush();
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', sprintf('/api/destructions/%s/confirm', $intent->getId()), [
            'totalWeightG' => 100,
            'nonCannabisRatio' => 0.60,
            'photoUrls' => ['https://example.test/photo.jpg'],
        ]);

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);
        self::assertStringContainsString('status "pending"', $this->client->getResponse()->getContent() ?: '');
    }

    public function testConfirmDestroyRejectsRatioBelowFiftyPercent(): void
    {
        [$user, $intent] = $this->createDestructionIntentFixture('-8 days', 'ROLE_ORG_ADMIN');
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', sprintf('/api/destructions/%s/confirm', $intent->getId()), [
            'totalWeightG' => 100,
            'nonCannabisRatio' => 0.49,
            'photoUrls' => ['https://example.test/photo.jpg'],
        ]);

        $this->assertStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString('50%', $this->client->getResponse()->getContent() ?: '');
    }

    public function testConfirmDestroyRejectsMissingPhotos(): void
    {
        [$user, $intent] = $this->createDestructionIntentFixture('-8 days', 'ROLE_ORG_ADMIN');
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', sprintf('/api/destructions/%s/confirm', $intent->getId()), [
            'totalWeightG' => 100,
            'nonCannabisRatio' => 0.60,
            'photoUrls' => [],
        ]);

        $this->assertStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString('photo', strtolower($this->client->getResponse()->getContent() ?: ''));
    }

    /**
     * @return array{0: User, 1: Plant}
     */
    public function testDestroyRejectsOperatorRole(): void
    {
        [$user, $plant] = $this->createHarvestFixture('PLANT-DESTROY-FORBIDDEN', 'ROLE_ORG_USER');
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', sprintf('/api/plants/%s/destroy', $plant->getId()), [
            'reason' => 'Mold contamination',
        ]);

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);
    }

    public function testConfirmDestroyRejectsOperatorRole(): void
    {
        [$user, $intent] = $this->createDestructionIntentFixture('-8 days', 'ROLE_ORG_USER');
        $this->authorizeClient($user);

        $this->apiJsonRequest('POST', sprintf('/api/destructions/%s/confirm', $intent->getId()), [
            'totalWeightG' => 100,
            'nonCannabisRatio' => 0.60,
            'photoUrls' => ['https://example.test/photo.jpg'],
        ]);

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);
    }

    /**
     * @return array{0: User, 1: Plant}
     */
    private function createHarvestFixture(string $rfidTag = 'PLANT-HARVEST', string $role = 'ROLE_ORG_USER'): array
    {
        $organization = $this->createOrganization('Org Harvest');
        $organization->setLicenseStatus(LicenseStatus::ACTIVE);
        $user = $this->createUser($organization, sprintf('%s@test.local', strtolower($rfidTag)), role: $role);
        $farm = $this->createFarm($organization, 'Farm Harvest');
        $room = $this->createRoom($farm, 'Flower Room', 'flower');
        $strain = $this->createStrain($organization, 'Harvest Strain');
        $plant = $this->createPlant($room, $user, $strain, PlantStage::FLOWERING, PlantStatus::ACTIVE, $rfidTag, '-30 days');

        $this->entityManager->flush();

        return [$user, $plant];
    }

    /**
     * @return array{0: User, 1: DestructionIntent}
     */
    private function createDestructionIntentFixture(string $declaredAt = 'now', string $role = 'ROLE_ORG_USER'): array
    {
        [$user, $plant] = $this->createHarvestFixture(sprintf('PLANT-DESTROY-%s', md5($declaredAt)), $role);

        $intent = new DestructionIntent();
        $intent->setPlant($plant);
        $intent->setTenantId($plant->getTenantId());
        $intent->setReason('Compliance issue');
        $intent->setDeclaredBy($user);

        if ($declaredAt !== 'now') {
            $ref = new \ReflectionObject($intent);
            $declaredAtProperty = $ref->getProperty('declaredAt');
            $declaredAtProperty->setAccessible(true);
            $declaredAtProperty->setValue($intent, new \DateTimeImmutable($declaredAt));

            $legalDateMinProperty = $ref->getProperty('legalDateMin');
            $legalDateMinProperty->setAccessible(true);
            $legalDateMinProperty->setValue($intent, new \DateTimeImmutable($declaredAt . ' +7 days'));
        }

        $this->entityManager->persist($intent);
        $this->entityManager->flush();

        return [$user, $intent];
    }
}
