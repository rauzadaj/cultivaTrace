<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Farm;
use App\Entity\HarvestRecord;
use App\Entity\Organization;
use App\Entity\Plant;
use App\Entity\PlantEvent;
use App\Entity\ReportExport;
use App\Entity\Room;
use App\Entity\Strain;
use App\Entity\User;
use App\Enum\LicenseStatus;
use Symfony\Component\HttpFoundation\Response;

final class ReportingControllerTest extends ApiTestCase
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
            PlantEvent::class,
            ReportExport::class,
        ]);
    }

    public function testHarvestSummaryCreatesPdfExportAndHistoryEntry(): void
    {
        [$admin, $farm, $room, $plant] = $this->createReportingFixture();
        $harvest = new HarvestRecord();
        $harvest->setTenantId($admin->getOrganization()->getId());
        $harvest->setPlant($plant);
        $harvest->setGrossWeightG('120.50');
        $harvest->setNetWeightG('95.20');
        $harvest->setHarvestedBy($admin);
        $harvest->setHarvestedAt(new \DateTimeImmutable('2026-04-01'));
        $this->entityManager->persist($harvest);
        $this->entityManager->flush();

        $this->authorizeClient($admin);
        $this->apiJsonRequest('POST', '/api/reporting/harvest-summary', [
            'dateFrom' => '2026-04-01',
            'dateTo' => '2026-04-30',
            'farmId' => (string) $farm->getId(),
            'roomId' => (string) $room->getId(),
        ]);

        $this->assertStatusCode(Response::HTTP_CREATED);
        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('harvest_summary', $payload['type']);
        self::assertSame('pdf', $payload['format']);

        $this->client->request('GET', '/api/reporting/exports');
        $this->assertStatusCode(Response::HTTP_OK);
        $history = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        $historyCount = $history['totalItems']
            ?? $history['hydra:totalItems']
            ?? (\is_array($history['member'] ?? null) ? \count($history['member']) : null)
            ?? (\is_array($history['hydra:member'] ?? null) ? \count($history['hydra:member']) : null)
            ?? (\array_is_list($history) ? \count($history) : 0);

        self::assertSame(1, $historyCount);
    }

    public function testAuditExportCreatesCsvAndCanBeDownloaded(): void
    {
        [$admin, , $room, $plant] = $this->createReportingFixture('audit@test.local');

        $event = new PlantEvent();
        $event->setTenantId($admin->getOrganization()->getId());
        $event->setPlant($plant);
        $event->setUser($admin);
        $event->setEventType('note');
        $event->setNotes('Observation critique');
        $event->setHashPrevious(str_repeat('0', 64));
        $event->setHashSelf(str_repeat('1', 64));
        $event->setIpAddress('127.0.0.1');
        $event->setOccurredAt(new \DateTimeImmutable('2026-04-02 10:00:00'));
        $this->entityManager->persist($event);
        $this->entityManager->flush();

        $this->authorizeClient($admin);
        $this->apiJsonRequest('POST', '/api/reporting/audit-export', [
            'dateFrom' => '2026-04-01',
            'dateTo' => '2026-04-30',
            'format' => 'csv',
        ]);

        $this->assertStatusCode(Response::HTTP_CREATED);
        $payload = json_decode($this->client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('audit_export', $payload['type']);
        self::assertSame('csv', $payload['format']);

        $this->client->request('GET', $payload['downloadUrl']);
        $this->assertStatusCode(Response::HTTP_OK);
        self::assertStringContainsString('attachment;', $this->client->getResponse()->headers->get('content-disposition', ''));
    }

    /**
     * @return array{0: User, 1: Farm, 2: Room, 3: Plant}
     */
    private function createReportingFixture(string $email = 'reporting@test.local'): array
    {
        $organization = $this->createOrganization('Org Reporting');
        $organization->setLicenseStatus(LicenseStatus::ACTIVE);
        $admin = $this->createUser($organization, $email, role: 'ROLE_ORG_ADMIN');
        $farm = $this->createFarm($organization, 'Farm Reporting');
        $room = $this->createRoom($farm, 'Room Reporting');
        $strain = $this->createStrain($organization, 'Blue Dream');
        $plant = $this->createPlant($room, $admin, $strain, rfidTag: 'PLANT-RPT');
        $this->entityManager->flush();

        return [$admin, $farm, $room, $plant];
    }
}
