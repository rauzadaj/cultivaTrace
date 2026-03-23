<?php

namespace App\Command;

use App\Compliance\CTLS\Export\CtlsExportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:export:ctls',
    description: 'Generate a CTLS monthly CSV from the internal plant export using strict validation.',
)]
final class ExportCtlsCommand extends Command
{
    public function __construct(
        private readonly CtlsExportService $exportService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('input', null, InputOption::VALUE_REQUIRED, 'Path to the current internal plant CSV export.')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Path to the target CTLS CSV file.')
            ->addOption('period', null, InputOption::VALUE_REQUIRED, 'Reporting period in YYYY-MM.')
            ->addOption('licence-id', null, InputOption::VALUE_OPTIONAL, 'Federal licence identifier used in CTLS.')
            ->addOption('business-stats', null, InputOption::VALUE_OPTIONAL, 'Path to a JSON file containing mandatory business statistics.')
            ->addOption('report', null, InputOption::VALUE_OPTIONAL, 'Path to the JSON validation report output.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $inputPath = (string) $input->getOption('input');
        $outputPath = (string) $input->getOption('output');
        $period = (string) $input->getOption('period');
        $reportPath = (string) ($input->getOption('report') ?: $outputPath . '.report.json');

        if ($inputPath === '' || $outputPath === '' || $period === '') {
            $io->error('The --input, --output and --period options are required.');

            return Command::INVALID;
        }

        $businessStats = [];
        $businessStatsPath = $input->getOption('business-stats');
        if (is_string($businessStatsPath) && $businessStatsPath !== '') {
            $contents = file_get_contents($businessStatsPath);
            if ($contents === false) {
                $io->error(sprintf('Unable to read business statistics JSON "%s".', $businessStatsPath));

                return Command::FAILURE;
            }

            $decoded = json_decode($contents, true);
            if (!is_array($decoded)) {
                $io->error('The business statistics file must contain a JSON object.');

                return Command::FAILURE;
            }

            $businessStats = $decoded;
        }

        $result = $this->exportService->exportFromCsv(
            $inputPath,
            $period,
            is_string($input->getOption('licence-id')) ? $input->getOption('licence-id') : null,
            $businessStats,
        );

        file_put_contents($reportPath, json_encode($result->report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if ($result->report->hasBlockingErrors()) {
            $io->error(sprintf('CTLS export blocked. Validation report written to %s.', $reportPath));
            $io->writeln(sprintf('Source rows: %d', $result->report->toArray()['sourceLineCount']));
            $io->writeln(sprintf('Exported rows: %d', $result->report->toArray()['exportedRowCount']));

            return Command::FAILURE;
        }

        file_put_contents($outputPath, (string) $result->csv);

        $io->success(sprintf('CTLS CSV written to %s', $outputPath));
        $io->writeln(sprintf('Validation report written to %s', $reportPath));

        return Command::SUCCESS;
    }
}
