<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Catalog\Mapping\CatalogMappingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:catalog:propose-mappings',
    description: 'Propose pending mappings between external catalog entries and internal genetics (code/name match). Reviewers approve or reject them afterwards.',
)]
final class ProposeCatalogMappingsCommand extends Command
{
    public function __construct(
        private readonly CatalogMappingService $catalogMappingService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $created = $this->catalogMappingService->proposeMappings();

        if ([] === $created) {
            $io->success('No new mappings to propose — every matchable external entry is already mapped.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($created as $mapping) {
            $rows[] = [
                $mapping->getExternalCatalogEntry()?->getExternalCode() ?? '',
                $mapping->getExternalCatalogEntry()?->getName() ?? '',
                $mapping->getGenetic()?->getCode() ?? '',
                $mapping->getStatus(),
            ];
        }

        $io->table(['External code', 'External name', 'Genetic code', 'Status'], $rows);
        $io->success(sprintf('Proposed %d pending mapping(s) for review.', count($created)));

        return Command::SUCCESS;
    }
}
