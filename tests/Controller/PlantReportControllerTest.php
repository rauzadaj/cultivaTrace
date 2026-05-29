<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\PlantReportController;
use App\Entity\Farm;
use App\Entity\HarvestRecord;
use App\Entity\InputRecord;
use App\Entity\Organization;
use App\Entity\Plant;
use App\Entity\PlantEvent;
use App\Entity\Room;
use App\Entity\Strain;
use App\Entity\User;
use App\Enum\RoomType;
use Symfony\Component\HttpFoundation\Response;

final class PlantReportControllerTest extends ApiTestCase
{
    protected function setUp(): void
    {
        if ($this->isGitHubActionsCi()) {
            $_ENV['GOTENBERG_URL'] = 'http://127.0.0.1:3000';
            $_SERVER['GOTENBERG_URL'] = 'http://127.0.0.1:3000';
        }

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
        ]);
    }

    private function isGitHubActionsCi(): bool
    {
        return ($_ENV['CI'] ?? $_SERVER['CI'] ?? null) === 'true'
            || ($_ENV['GITHUB_ACTIONS'] ?? $_SERVER['GITHUB_ACTIONS'] ?? null) === 'true';
    }

    public function testPlantPdfReportDownloadsInLessThanTenSeconds(): void
    {
        [$user, $plant] = $this->createReportFixture();
        $this->authorizeClient($user);

        $startedAt = microtime(true);
        $this->client->request('GET', sprintf('/api/plants/%s/report', $plant->getId()));
        $duration = microtime(true) - $startedAt;

        if ($this->client->getResponse()->getStatusCode() === Response::HTTP_SERVICE_UNAVAILABLE) {
            self::markTestSkipped('Gotenberg service is not available in this environment.');
        }
        $this->assertStatusCode(Response::HTTP_OK);
        self::assertLessThan(10, $duration);
        self::assertStringContainsString('application/pdf', $this->client->getResponse()->headers->get('Content-Type', ''));
        self::assertStringContainsString('attachment;', $this->client->getResponse()->headers->get('Content-Disposition', ''));
        self::assertGreaterThan(0, (int) $this->client->getResponse()->headers->get('Content-Length', '0'));
    }

    public function testCtsCsvExportIncludesUtf8BomAndExpectedColumns(): void
    {
        [$admin] = $this->createReportFixture('ROLE_ORG_ADMIN');
        $this->authorizeClient($admin);

        $this->client->request('GET', '/api/compliance/ctsreport?month=2026-03');

        $this->assertStatusCode(Response::HTTP_OK);

        $response = $this->client->getResponse();
        // KernelBrowser already consumed the StreamedResponse callback when
        // building the response, so read the captured body instead of re-streaming.
        $content = (string) $this->client->getInternalResponse()->getContent();

        self::assertStringContainsString('text/csv', $response->headers->get('Content-Type', ''));
        self::assertStringStartsWith("\xEF\xBB\xBF", $content);
        self::assertStringContainsString('"Report Period","Plant ID",Strain,"Cannabis Type",Stage,Status,"Germinated At",Room,"Harvested At","Gross Weight (g)","Net Weight (g)","Destroyed At","Destruction Reason",Quarantined,Notes', $content);
        self::assertStringContainsString('2026-03', $content);
        self::assertStringContainsString('Report Strain', $content);
    }

    public function testCtsCsvExportIsForbiddenForNonAdminUser(): void
    {
        [, , $organization] = $this->createReportFixture('ROLE_ORG_ADMIN');
        $member = $this->createUser($organization, 'cts-member@test.local', role: 'ROLE_ORG_USER');
        $this->entityManager->flush();

        $this->authorizeClient($member);
        $this->client->request('GET', '/api/compliance/ctsreport?month=2026-03');

        $this->assertStatusCode(Response::HTTP_FORBIDDEN);
    }

    /**
     * @return array{0: User, 1: Plant, 2: Organization}
     */
    private function createReportFixture(string $role = 'ROLE_ORG_USER'): array
    {
        $organization = $this->createOrganization('Org Reports');
        $user = $this->createUser($organization, 'reports@test.local', role: $role);
        $farm = $this->createFarm($organization, 'Report Farm');
        $room = $this->createRoom($farm, 'Report Room', RoomType::Flower);
        $strain = $this->createStrain($organization, 'Report Strain');
        $plant = $this->createPlant($room, $user, $strain, rfidTag: 'PLANT-REPORT', germinatedAt: '2026-03-01');

        $this->entityManager->flush();

        /** @var PlantEventRepository $eventRepository */
        $eventRepository = $this->entityManager->getRepository(PlantEvent::class);
        $eventRepository->appendEvent($plant, 'germination', $user, ['stage' => 'germination']);
        $eventRepository->appendEvent($plant, 'note', $user, ['message' => 'Canopy inspection'], 'Canopy stable');
        $eventRepository->appendEvent($plant, 'input_record', $user, ['product' => 'Nutrient Mix', 'quantity' => 20, 'unit' => 'ml']);

        $this->entityManager->clear();

        /** @var Plant $reloadedPlant */
        $reloadedPlant = $this->entityManager->getRepository(Plant::class)->findOneBy(['rfidTag' => 'PLANT-REPORT']);

        /** @var Organization $reloadedOrganization */
        $reloadedOrganization = $this->entityManager->getRepository(Organization::class)->find($organization->getId());

        return [$user, $reloadedPlant, $reloadedOrganization];
    }
}
