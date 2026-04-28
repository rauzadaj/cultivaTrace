<?php

namespace App\Tests\Domain\Cultivation\Model;

use App\Domain\Cultivation\Enum\JournalEntryType;
use App\Domain\Cultivation\Exception\JournalEntryAppendOnlyViolation;
use App\Domain\Cultivation\Model\Crop;
use App\Domain\Cultivation\Model\Genetic;
use App\Domain\Cultivation\Model\JournalEntry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class JournalEntryTest extends TestCase
{
    public function testJournalEntryCanBeBuiltBeforeItIsSealed(): void
    {
        $tenantId = Uuid::v4();
        $crop = (new Crop())
            ->setTenantId($tenantId)
            ->setBatchCode('LOT-2026-JRN-001')
            ->setDisplayName('Journal Mutable')
            ->setGenetic(
                (new Genetic())
                    ->setCode('JRN-01')
                    ->setName('Journal Genetic'),
            );

        $entry = (new JournalEntry())
            ->setCrop($crop)
            ->setType(JournalEntryType::Observation)
            ->setNotes('Initial note')
            ->setMetadata(['source' => 'unit-test']);

        self::assertFalse($entry->isSealed());
        self::assertSame('Initial note', $entry->getNotes());
        self::assertSame(['source' => 'unit-test'], $entry->getMetadata());
        self::assertSame((string) $tenantId, (string) $entry->getTenantId());
    }

    public function testSealedJournalEntryRejectsMutation(): void
    {
        $entry = new JournalEntry();
        $entry->seal();

        $this->expectException(JournalEntryAppendOnlyViolation::class);

        $entry->setNotes('Mutation after persist');
    }
}
