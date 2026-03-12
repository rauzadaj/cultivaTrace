<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Application\Catalog\SeedCatalog\SeedCatalogEntry;
use App\Application\Catalog\SeedCatalog\SeedCatalogProvider;
use App\Command\SyncSeedCatalogCommand;
use App\Domain\Cultivation\Model\Genetic;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SyncSeedCatalogCommandTest extends TestCase
{
    public function testItExportsASnapshotWithoutTouchingDoctrineWhenNoUpsertIsEnabled(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getRepository');
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $provider = new class() implements SeedCatalogProvider {
            public function fetchEntries(): array
            {
                return [
                    new SeedCatalogEntry(
                        code: 'HSC_TEST',
                        name: 'Test Cultivar',
                        vendor: 'Humboldt Seed Company',
                        genetics: 'Parent A x Parent B',
                        description: 'Snapshot-only export entry.',
                        imageUrl: 'https://example.test/image.jpg',
                        sourceUrl: 'https://example.test/source',
                        sourceModifiedAt: '2026-03-12T00:00:00+00:00',
                        markets: ['california', 'canada'],
                        metadata: ['source' => 'fixture'],
                    ),
                ];
            }
        };

        $exportPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cultivatrace-seed-catalog-export.json';
        @unlink($exportPath);

        $commandTester = new CommandTester(new SyncSeedCatalogCommand($provider, $entityManager));

        self::assertSame(Command::SUCCESS, $commandTester->execute([
            '--export-path' => $exportPath,
            '--no-upsert' => true,
        ]));
        self::assertFileExists($exportPath);

        /** @var array{provider: string, jurisdictions: list<string>, entries: list<array{name: string}>} $payload */
        $payload = json_decode((string) file_get_contents($exportPath), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('humboldtseedcompany.com', $payload['provider']);
        self::assertSame(['california', 'canada'], $payload['jurisdictions']);
        self::assertCount(1, $payload['entries']);
        self::assertSame('Test Cultivar', $payload['entries'][0]['name']);

        @unlink($exportPath);
    }

    public function testItUpsertsEntriesIntoGenetics(): void
    {
        $existing = (new Genetic())
            ->setCode('HSC_EXISTING')
            ->setName('Legacy Name')
            ->setVendor('Legacy Vendor')
            ->setMetadata(['legacy' => true]);

        $repository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $repository->expects(self::exactly(2))
            ->method('findOneBy')
            ->willReturnOnConsecutiveCalls($existing, null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))
            ->method('getRepository')
            ->with(Genetic::class)
            ->willReturn($repository);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(Genetic::class));
        $entityManager->expects(self::once())->method('flush');

        $provider = new class() implements SeedCatalogProvider {
            public function fetchEntries(): array
            {
                return [
                    new SeedCatalogEntry(
                        code: 'HSC_EXISTING',
                        name: 'Updated Cultivar',
                        vendor: 'Humboldt Seed Company',
                        genetics: 'Parent A x Parent B',
                        description: 'Updated description.',
                        imageUrl: 'https://example.test/existing.jpg',
                        sourceUrl: 'https://example.test/existing',
                        sourceModifiedAt: '2026-03-12T00:00:00+00:00',
                        markets: ['california', 'canada'],
                        metadata: ['source' => 'fixture'],
                    ),
                    new SeedCatalogEntry(
                        code: 'HSC_NEW',
                        name: 'New Cultivar',
                        vendor: 'Humboldt Seed Company',
                        genetics: 'Parent C x Parent D',
                        description: 'New description.',
                        imageUrl: 'https://example.test/new.jpg',
                        sourceUrl: 'https://example.test/new',
                        sourceModifiedAt: '2026-03-12T00:00:00+00:00',
                        markets: ['california', 'canada'],
                        metadata: ['source' => 'fixture'],
                    ),
                ];
            }
        };

        $exportPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cultivatrace-seed-catalog-upsert.json';
        @unlink($exportPath);

        $commandTester = new CommandTester(new SyncSeedCatalogCommand($provider, $entityManager));

        self::assertSame(Command::SUCCESS, $commandTester->execute([
            '--export-path' => $exportPath,
        ]));
        self::assertSame('Updated Cultivar', $existing->getName());
        self::assertSame('Humboldt Seed Company', $existing->getVendor());
        self::assertSame('HSC_EXISTING', $existing->getMetadata()['code']);
        self::assertSame('Updated Cultivar', $existing->getMetadata()['name']);
        self::assertSame(['source' => 'fixture'], $existing->getMetadata()['metadata']);

        @unlink($exportPath);
    }
}
