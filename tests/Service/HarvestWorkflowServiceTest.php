<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\HarvestRecord;
use App\Entity\Plant;
use App\Entity\User;
use App\Enum\PlantStatus;
use App\Repository\PlantEventRepository;
use App\Service\HarvestWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class HarvestWorkflowServiceTest extends TestCase
{
    private HarvestWorkflowService $service;
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;
    /** @var PlantEventRepository&MockObject */
    private PlantEventRepository $eventRepository;

    protected function setUp(): void
    {
        $this->em              = $this->createMock(EntityManagerInterface::class);
        $this->eventRepository = $this->createMock(PlantEventRepository::class);
        $this->service         = new HarvestWorkflowService($this->em, $this->eventRepository);
    }

    private function activePlant(): Plant
    {
        $plant = new Plant();
        $plant->setTenantId(Uuid::v4());

        return $plant;
    }

    public function testMissingGrossWeightThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/grossWeightG.*obligatoires/');

        $this->service->harvest($this->activePlant(), new User(), ['netWeightG' => 50.0]);
    }

    public function testMissingNetWeightThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/netWeightG.*obligatoires/');

        $this->service->harvest($this->activePlant(), new User(), ['grossWeightG' => 100.0]);
    }

    public function testZeroGrossWeightThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/grossWeightG.*strictement positif/');

        $this->service->harvest($this->activePlant(), new User(), ['grossWeightG' => 0, 'netWeightG' => 0]);
    }

    public function testNegativeGrossWeightThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/grossWeightG.*strictement positif/');

        $this->service->harvest($this->activePlant(), new User(), ['grossWeightG' => -10.0, 'netWeightG' => 5.0]);
    }

    public function testZeroNetWeightThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/netWeightG.*strictement positif/');

        $this->service->harvest($this->activePlant(), new User(), ['grossWeightG' => 100.0, 'netWeightG' => 0]);
    }

    public function testNetWeightExceedsGrossWeightThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/poids net.*supérieur.*brut/');

        $this->service->harvest($this->activePlant(), new User(), ['grossWeightG' => 80.0, 'netWeightG' => 100.0]);
    }

    public function testNonNumericWeightThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/strictement positif/');

        $this->service->harvest($this->activePlant(), new User(), ['grossWeightG' => 'abc', 'netWeightG' => 50.0]);
    }

    public function testHarvestOnInactivePlantThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $plant = $this->activePlant();
        $plant->setStatus(PlantStatus::HARVESTED);

        $this->service->harvest($plant, new User(), ['grossWeightG' => 100.0, 'netWeightG' => 80.0]);
    }

    public function testValidHarvestReturnsRecord(): void
    {
        $this->em->expects(self::once())->method('beginTransaction');
        $this->em->expects(self::once())->method('persist');
        $this->em->expects(self::once())->method('flush');
        $this->em->expects(self::once())->method('commit');
        $this->eventRepository->expects(self::once())->method('appendEvent');

        $result = $this->service->harvest(
            $this->activePlant(),
            new User(),
            ['grossWeightG' => 120.5, 'netWeightG' => 95.2],
        );

        self::assertInstanceOf(HarvestRecord::class, $result);
        self::assertSame('120.5', $result->getGrossWeightG());
        self::assertSame('95.2', $result->getNetWeightG());
    }
}
