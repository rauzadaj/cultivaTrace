<?php

namespace App\Tests\Application\Cultivation\Workflow;

use App\Application\Cultivation\Workflow\CropLifecycleManager;
use App\Domain\Cultivation\Enum\CropStage;
use App\Domain\Cultivation\Exception\InvalidCropStageTransition;
use App\Domain\Cultivation\Model\Crop;
use App\Domain\Cultivation\Model\Genetic;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Workflow\Registry;

final class CropLifecycleManagerTest extends KernelTestCase
{
    public function testWorkflowTransitionsCropAcrossLifecycle(): void
    {
        self::bootKernel();

        $crop = (new Crop())
            ->setBatchCode('LOT-2026-WF-001')
            ->setDisplayName('Workflow Crop')
            ->setGenetic(
                (new Genetic())
                    ->setCode('WF-01')
                    ->setName('Workflow Genetic'),
            );
        $manager = $this->buildManager($crop);

        $manager->moveToVegetative($crop);
        $manager->moveToFlowering($crop);
        $manager->harvest($crop, 420);

        self::assertSame(CropStage::Harvest, $crop->getCurrentStage());
        self::assertSame(420, $crop->getFinalYieldGrams());
        self::assertCount(3, $crop->getJournalEntries());
    }

    public function testWorkflowRejectsInvalidTransitionOrder(): void
    {
        self::bootKernel();

        $crop = (new Crop())
            ->setBatchCode('LOT-2026-WF-002')
            ->setDisplayName('Workflow Guard')
            ->setGenetic(
                (new Genetic())
                    ->setCode('WF-02')
                    ->setName('Workflow Guard Genetic'),
            );
        $manager = $this->buildManager($crop);

        $this->expectException(InvalidCropStageTransition::class);

        $manager->moveToFlowering($crop);
    }

    private function buildManager(Crop $crop): CropLifecycleManager
    {
        /** @var Registry $registry */
        $registry = static::getContainer()->get('test.workflow.registry');

        return new CropLifecycleManager($registry->get($crop, 'crop_lifecycle'));
    }
}
