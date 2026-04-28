<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Plant;

use App\Domain\Plant\Plant;
use App\Domain\Plant\PlantOriginType;
use App\Domain\Plant\PlantStatus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Workflow\WorkflowInterface;

final class PlantWorkflowTest extends KernelTestCase
{
    private WorkflowInterface $workflow;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->workflow = self::getContainer()->get('state_machine.plant_lifecycle');
    }

    public function testLinearLifecycleTransitionsAreAllowed(): void
    {
        $plant = Plant::create(
            Uuid::v4(),
            'Amnesia Haze',
            PlantOriginType::SEED,
            new \DateTimeImmutable('-4 days'),
            'Room A / Table 3',
        );

        self::assertTrue($this->workflow->can($plant, 'grow'));
        $this->workflow->apply($plant, 'grow');
        self::assertSame(PlantStatus::VEGETATIVE, $plant->getStatus());

        self::assertTrue($this->workflow->can($plant, 'flower'));
        $this->workflow->apply($plant, 'flower');
        self::assertSame(PlantStatus::FLOWERING, $plant->getStatus());

        self::assertTrue($this->workflow->can($plant, 'harvest'));
        $this->workflow->apply($plant, 'harvest');
        self::assertSame(PlantStatus::HARVESTED, $plant->getStatus());
    }

    public function testQuarantineIsReachableFromAnyState(): void
    {
        $plant = Plant::create(
            Uuid::v4(),
            'Critical Mass',
            PlantOriginType::CLONE,
            new \DateTimeImmutable('-9 days'),
            'Room B / Rack 1',
        );

        self::assertTrue($this->workflow->can($plant, 'quarantine'));
        $this->workflow->apply($plant, 'quarantine');

        self::assertSame(PlantStatus::QUARANTINE, $plant->getStatus());
        self::assertTrue($this->workflow->can($plant, 'release_to_vegetative'));
        self::assertTrue($this->workflow->can($plant, 'release_to_flowering'));
    }

    public function testDestroyIsReachableFromAnyState(): void
    {
        $plant = Plant::create(
            Uuid::v4(),
            'Northern Lights',
            PlantOriginType::TISSUE,
            new \DateTimeImmutable('-2 days'),
            'Lab 2 / Shelf 7',
        );

        self::assertTrue($this->workflow->can($plant, 'destroy'));
        $this->workflow->apply($plant, 'destroy');

        self::assertSame(PlantStatus::DESTROYED, $plant->getStatus());
    }
}
