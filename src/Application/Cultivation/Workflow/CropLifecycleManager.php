<?php

namespace App\Application\Cultivation\Workflow;

use App\Domain\Cultivation\Enum\CropStage;
use App\Domain\Cultivation\Exception\InvalidCropStageTransition;
use App\Domain\Cultivation\Model\Crop;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * @internal
 * @todo(mvp-deferred) Crop lifecycle workflow — deferred pending Crop domain promotion.
 *                     See src/Domain/Cultivation/DEFERRED.md.
 */
final readonly class CropLifecycleManager
{
    public function __construct(
        #[Autowire(service: 'state_machine.crop_lifecycle')]
        private WorkflowInterface $workflow,
    ) {
    }

    public function moveToVegetative(Crop $crop): void
    {
        $this->apply($crop, 'start_vegetative', CropStage::Veg);
    }

    public function moveToFlowering(Crop $crop): void
    {
        $this->apply($crop, 'start_flowering', CropStage::Flower);
    }

    public function harvest(Crop $crop, int $finalYieldGrams, ?\DateTimeImmutable $harvestedAt = null): void
    {
        $this->apply($crop, 'harvest', CropStage::Harvest);
        $crop->markHarvested($finalYieldGrams, $harvestedAt);
    }

    private function apply(Crop $crop, string $transition, CropStage $targetStage): void
    {
        $from = $crop->getCurrentStage();

        if (!$this->workflow->can($crop, $transition)) {
            throw InvalidCropStageTransition::between($from, $targetStage);
        }

        $this->workflow->apply($crop, $transition);
        $crop->recordStageTransition($from, $targetStage);
    }
}
