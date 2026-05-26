<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DestructionIntent;
use App\Entity\Plant;
use App\Entity\User;
use App\Enum\PlantStatus;
use App\Repository\PlantEventRepository;
use App\Service\DestructionWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class DestructionWorkflowServiceTest extends TestCase
{
    private DestructionWorkflowService $service;
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;
    /** @var PlantEventRepository&MockObject */
    private PlantEventRepository $eventRepository;

    protected function setUp(): void
    {
        $this->em              = $this->createMock(EntityManagerInterface::class);
        $this->eventRepository = $this->createMock(PlantEventRepository::class);
        $this->service         = new DestructionWorkflowService($this->em, $this->eventRepository);
    }

    private function activePlant(): Plant
    {
        $plant = new Plant();
        $plant->setTenantId(Uuid::v4());

        return $plant;
    }

    /** Returns a DestructionIntent whose legalDateMin is in the past so canBeConfirmed() = true */
    private function confirmableIntent(): DestructionIntent
    {
        $intent = new DestructionIntent();
        $intent->setPlant($this->activePlant());
        $intent->setTenantId(Uuid::v4());

        $ref = new \ReflectionProperty(DestructionIntent::class, 'legalDateMin');
        $ref->setValue($intent, new \DateTimeImmutable('-1 day'));

        return $intent;
    }

    // ── declareIntent ──────────────────────────────────────────────────────────

    public function testDeclareIntentOnInactivePlantThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $plant = $this->activePlant();
        $plant->setStatus(PlantStatus::HARVESTED);

        $this->service->declareIntent($plant, new User(), 'Mold contamination');
    }

    public function testDeclareIntentWithEmptyReasonThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/raison.*obligatoire/');

        $this->service->declareIntent($this->activePlant(), new User(), '   ');
    }

    public function testDeclareIntentPersistsAndReturnsIntent(): void
    {
        $this->em->expects(self::once())->method('persist');
        $this->em->expects(self::once())->method('flush');
        $this->eventRepository->expects(self::once())->method('appendEvent');

        $result = $this->service->declareIntent($this->activePlant(), new User(), 'Mold contamination');

        self::assertInstanceOf(DestructionIntent::class, $result);
        self::assertSame('Mold contamination', $result->getReason());
    }

    // ── confirmIntent ──────────────────────────────────────────────────────────

    public function testConfirmIntentWithZeroWeightThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/totalWeightG.*strictly positive/');

        $this->service->confirmIntent($this->confirmableIntent(), new User(), [
            'totalWeightG' => 0,
            'nonCannabisRatio' => 0.8,
            'photoUrls' => ['http://example.com/photo.jpg'],
        ]);
    }

    public function testConfirmIntentWithNegativeWeightThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/totalWeightG.*strictly positive/');

        $this->service->confirmIntent($this->confirmableIntent(), new User(), [
            'totalWeightG' => -5.0,
            'nonCannabisRatio' => 0.8,
            'photoUrls' => ['http://example.com/photo.jpg'],
        ]);
    }

    public function testConfirmIntentWithRatioBelowFiftyPercentThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/50%/');

        $this->service->confirmIntent($this->confirmableIntent(), new User(), [
            'totalWeightG' => 100.0,
            'nonCannabisRatio' => 0.49,
            'photoUrls' => ['http://example.com/photo.jpg'],
        ]);
    }

    public function testConfirmIntentWithRatioAboveOneThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/entre 0 et 1/');

        $this->service->confirmIntent($this->confirmableIntent(), new User(), [
            'totalWeightG' => 100.0,
            'nonCannabisRatio' => 1.5,
            'photoUrls' => ['http://example.com/photo.jpg'],
        ]);
    }

    public function testConfirmIntentWithNonNumericRatioThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/entre 0 et 1/');

        $this->service->confirmIntent($this->confirmableIntent(), new User(), [
            'totalWeightG' => 100.0,
            'nonCannabisRatio' => 'high',
            'photoUrls' => ['http://example.com/photo.jpg'],
        ]);
    }

    public function testConfirmIntentWithoutPhotosThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/photo.*obligatoire/');

        $this->service->confirmIntent($this->confirmableIntent(), new User(), [
            'totalWeightG' => 100.0,
            'nonCannabisRatio' => 0.75,
            'photoUrls' => [],
        ]);
    }

    public function testConfirmIntentBeforeLegalDateThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Destruction impossible/');

        $intent = new DestructionIntent();
        $intent->setPlant($this->activePlant());
        $intent->setTenantId(Uuid::v4());
        // legalDateMin defaults to +7 days, so canBeConfirmed() = false

        $this->service->confirmIntent($intent, new User(), [
            'totalWeightG' => 100.0,
            'nonCannabisRatio' => 0.75,
            'photoUrls' => ['http://example.com/photo.jpg'],
        ]);
    }

    public function testValidConfirmationSetsPlantDestroyed(): void
    {
        $this->em->expects(self::once())->method('beginTransaction');
        $this->em->expects(self::once())->method('flush');
        $this->em->expects(self::once())->method('commit');
        $this->eventRepository->expects(self::once())->method('appendEvent');

        $intent = $this->confirmableIntent();

        $this->service->confirmIntent($intent, new User(), [
            'totalWeightG' => 100.0,
            'nonCannabisRatio' => 0.75,
            'photoUrls' => ['http://example.com/photo.jpg'],
        ]);

        self::assertSame(PlantStatus::DESTROYED, $intent->getPlant()->getStatus());
    }
}
