<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Farm;
use App\Entity\HarvestRecord;
use App\Entity\Organization;
use App\Entity\Plant;
use App\Entity\PlantEvent;
use App\Entity\Room;
use App\Entity\Strain;
use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;

final class CTSReportControllerTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetSchema([
            Organization::class, User::class, Farm::class, Room::class, Strain::class,
            Plant::class, HarvestRecord::class, PlantEvent::class,
        ]);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidMonths(): iterable
    {
        foreach (['2026-00', '2026-13', '0000-01', '2026-2', 'invalid', "2026-02\n"] as $month) {
            yield $month => [$month];
        }
    }

    #[DataProvider('invalidMonths')]
    public function testInvalidMonthReturnsBadRequest(string $month): void
    {
        $user = $this->createUser($this->createOrganization('Org'), 'cts@test.local');
        $this->entityManager->flush();
        $this->authorizeClient($user);
        $this->client->request('GET', '/api/compliance/ctsreport?' . http_build_query(['month' => $month]));
        $this->assertStatusCode(400);
    }

    public function testAnonymousCannotExport(): void
    {
        $this->client->request('GET', '/api/compliance/ctsreport?month=2026-02');
        $this->assertStatusCode(401);
    }

    public function testUserWithoutOrganizationCannotExport(): void
    {
        $controller = new \App\Controller\CTSReportController($this->entityManager);
        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $controller(new \Symfony\Component\HttpFoundation\Request(), new User());
    }

    public function testExportIsTenantScopedAndEscapesSpreadsheetText(): void
    {
        $orgA = $this->createOrganization('Org A');
        $userA = $this->createUser($orgA, 'cts-a@test.local');
        $roomA = $this->createRoom($this->createFarm($orgA, 'Farm A'), '=1+1');
        $strainA = $this->createStrain($orgA, '@SUM(1,2)');
        $this->createPlant($roomA, $userA, $strainA, germinatedAt: '2026-02-28');
        $future = $this->createStrain($orgA, 'FUTURE-PLANT');
        $this->createPlant($roomA, $userA, $future, rfidTag: 'FUTURE', germinatedAt: '2026-03-01');
        $orgB = $this->createOrganization('Org B');
        $userB = $this->createUser($orgB, 'cts-b@test.local');
        $roomB = $this->createRoom($this->createFarm($orgB, 'Farm B'), 'SECRET-TENANT-B');
        $this->createPlant($roomB, $userB, germinatedAt: '2026-02-01');
        $this->entityManager->flush();
        $this->authorizeClient($userA);

        $this->client->request('GET', '/api/compliance/ctsreport?month=2026-02');
        $this->assertStatusCode(200);
        $content = $this->client->getInternalResponse()->getContent();
        self::assertStringContainsString("'=1+1", $content);
        self::assertStringContainsString("'@SUM(1,2)", $content);
        self::assertStringNotContainsString('SECRET-TENANT-B', $content);
        self::assertStringNotContainsString('FUTURE-PLANT', $content);
        self::assertStringContainsString('2026-02-28', $content);
        self::assertStringContainsString('cts-report-2026-02.csv', $this->client->getResponse()->headers->get('Content-Disposition', ''));
    }
}
