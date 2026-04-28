<?php

namespace App\Tests\Domain\Cultivation\Model;

use App\Domain\Cultivation\Enum\CropStage;
use App\Domain\Cultivation\Enum\JournalEntryType;
use App\Domain\Cultivation\Model\Crop;
use App\Domain\Cultivation\Model\Genetic;
use App\Domain\Cultivation\Model\JournalEntry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CropTest extends TestCase
{
    public function testCropSupportsSequentialLifecycleTransitions(): void
    {
        $genetic = (new Genetic())
            ->setCode('OGK-01')
            ->setName('OG Kush');

        $crop = (new Crop())
            ->setBatchCode('LOT-2026-001')
            ->setDisplayName('OG Kush - Room A')
            ->setGenetic($genetic);

        self::assertSame(CropStage::Seedling, $crop->getCurrentStage());

        $crop->moveToVegetativeStage();
        $crop->moveToFloweringStage();
        $crop->harvest(680);

        self::assertSame(CropStage::Harvest, $crop->getCurrentStage());
        self::assertSame(680, $crop->getFinalYieldGrams());
        self::assertNotNull($crop->getHarvestedAt());
    }

    public function testCropRecordsWorkflowStageTransitionIntoJournal(): void
    {
        $tenantId = Uuid::v4();
        $crop = (new Crop())
            ->setTenantId($tenantId)
            ->setBatchCode('LOT-2026-002')
            ->setDisplayName('Workflow Transition')
            ->setGenetic(
                (new Genetic())
                    ->setCode('RNTZ-01')
                    ->setName('Runtz'),
            );

        $crop->recordStageTransition(CropStage::Seedling, CropStage::Veg);

        self::assertCount(1, $crop->getJournalEntries());
        self::assertSame(JournalEntryType::StageTransition, $crop->getJournalEntries()->first()->getType());
        self::assertSame((string) $tenantId, (string) $crop->getJournalEntries()->first()->getTenantId());
    }

    public function testJournalEntryAttachmentIsAppendOnlyFromCropAggregate(): void
    {
        $crop = (new Crop())
            ->setBatchCode('LOT-2026-003')
            ->setDisplayName('Append Only Crop')
            ->setGenetic(
                (new Genetic())
                    ->setCode('GDP-01')
                    ->setName('Granddaddy Purple'),
            );

        $entry = (new JournalEntry())
            ->setType(JournalEntryType::Irrigation)
            ->setCrop($crop);

        $crop->addJournalEntry($entry);

        self::assertCount(1, $crop->getJournalEntries());
        self::assertSame($crop, $entry->getCrop());
    }

    public function testSettingTenantIdPropagatesToAttachedJournalEntries(): void
    {
        $crop = (new Crop())
            ->setBatchCode('LOT-2026-004')
            ->setDisplayName('Tenant Propagation Crop')
            ->setGenetic(
                (new Genetic())
                    ->setCode('TEN-01')
                    ->setName('Tenant Genetic'),
            );

        $entry = (new JournalEntry())
            ->setType(JournalEntryType::Observation)
            ->setCrop($crop);

        $crop->addJournalEntry($entry);
        $crop->setTenantId(Uuid::v4());

        self::assertSame((string) $crop->getTenantId(), (string) $entry->getTenantId());
    }
}
