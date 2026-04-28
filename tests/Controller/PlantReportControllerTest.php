<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\CTSReportController;
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
use Symfony\Component\HttpFoundation\Request;

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

        $this->assertStatusCode(Response::HTTP_OK);
        self::assertLessThan(10, $duration);
        self::assertStringContainsString('application/pdf', $this->client->getResponse()->headers->get('Content-Type', ''));
        self::assertStringContainsString('attachment;', $this->client->getResponse()->headers->get('Content-Disposition', ''));
        self::assertGreaterThan(0, (int) $this->client->getResponse()->headers->get('Content-Length', '0'));
    }

    public function testCtsCsvExportIncludesUtf8BomAndExpectedColumns(): void
    {
        [$user] = $this->createReportFixture();

        $controller = new CTSReportController($this->entityManager);
        $response = $controller->__invoke(new Request(['month' => '2026-03']), $user);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        ob_start();
        $response->sendContent();
        $content = (string) ob_get_clean();

        self::assertStringContainsString('text/csv', $response->headers->get('Content-Type', ''));
        self::assertStringStartsWith("\xEF\xBB\xBF", $content);
        self::assertStringContainsString('"Report Period","Plant ID",Strain,"Cannabis Type",Stage,Status,"Germinated At",Room,"Harvested At","Gross Weight (g)","Net Weight (g)","Destroyed At","Destruction Reason",Quarantined,Notes', $content);
        self::assertStringContainsString('2026-03', $content);
        self::assertStringContainsString('Report Strain', $content);
    }

    /**
     * @return array{0: User, 1: Plant}
     */
    private function createReportFixture(): array
    {
        $organization = $this->createOrganization('Org Reports');
        $user = $this->createUser($organization, 'reports@test.local');
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

        return [$user, $reloadedPlant];
    }
}
