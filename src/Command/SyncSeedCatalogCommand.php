<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Catalog\SeedCatalog\SeedCatalogProvider;
use App\Domain\Cultivation\Model\Genetic;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-seed-catalog',
    description: 'Synchronize the verified cannabis seed catalog snapshot into Genetic metadata and export a JSON snapshot.',
)]
final class SyncSeedCatalogCommand extends Command
{
    private const DEFAULT_EXPORT_PATH = 'catalog/seed-catalog/humboldt-california-canada.json';

    public function __construct(
        private readonly SeedCatalogProvider $seedCatalogProvider,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('export-path', null, InputOption::VALUE_REQUIRED, 'Path of the JSON snapshot to write.', self::DEFAULT_EXPORT_PATH)
            ->addOption('no-upsert', null, InputOption::VALUE_NONE, 'Skip Genetic upsert and only write the JSON snapshot.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $entries = $this->seedCatalogProvider->fetchEntries();

        if ([] === $entries) {
            $io->error('No seed catalog entries were fetched from the upstream source.');

            return Command::FAILURE;
        }

        if (!$input->getOption('no-upsert')) {
            foreach ($entries as $entry) {
                /** @var Genetic|null $genetic */
                $genetic = $this->entityManager->getRepository(Genetic::class)->findOneBy(['code' => $entry->code]);

                if (!$genetic instanceof Genetic) {
                    $genetic = (new Genetic())->setCode($entry->code);
                    $this->entityManager->persist($genetic);
                }

                $genetic
                    ->setName($entry->name)
                    ->setVendor($entry->vendor)
                    ->setMetadata($entry->toArray());
            }

            $this->entityManager->flush();
        }

        $exportPath = (string) $input->getOption('export-path');
        $this->writeSnapshot($exportPath, $entries);

        $io->success(sprintf('Synchronized %d catalog entries and wrote %s.', count($entries), $exportPath));

        return Command::SUCCESS;
    }

    /**
     * @param list<\App\Application\Catalog\SeedCatalog\SeedCatalogEntry> $entries
     */
    private function writeSnapshot(string $exportPath, array $entries): void
    {
        $directory = dirname($exportPath);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create export directory: %s', $directory));
        }

        $payload = [
            'provider' => 'humboldtseedcompany.com',
            'generatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'jurisdictions' => ['california', 'canada'],
            'entries' => array_map(
                static fn ($entry): array => $entry->toArray(),
                $entries,
            ),
        ];

        file_put_contents(
            $exportPath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }
}
