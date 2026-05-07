<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\HealthCanadaLicensedProducer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Downloads Health Canada's authorized licensed producers CSV and upserts
 * it into the health_canada_registry table for offline KYB verification.
 *
 * Source dataset:
 *   https://health-products.canada.ca/api/dataset/
 *   95c29d8f-3688-4a37-aca0-1a50af7b1c86
 *
 * Run daily via scheduler (see SyncHealthCanadaRegistryScheduler).
 * Can also be run manually: php bin/console app:sync-health-canada-registry
 */
#[AsCommand(
    name: 'app:sync-health-canada-registry',
    description: 'Sync Health Canada authorized licensed producers list for KYB verification',
)]
final class SyncHealthCanadaRegistryCommand extends Command
{
    private const HC_CSV_URL = 'https://health-products.canada.ca/api/dataset/95c29d8f-3688-4a37-aca0-1a50af7b1c86?lang=en&type=csv';

    // Column indexes in the HC CSV (0-based). Verify against the live CSV if format changes.
    private const COL_LICENSE_NUMBER = 0;
    private const COL_COMPANY_NAME   = 1;
    private const COL_LICENSE_TYPE   = 2;
    private const COL_STATUS         = 3;
    private const COL_PROVINCE       = 4;
    private const COL_ISSUED_AT      = 5;
    private const COL_EXPIRES_AT     = 6;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Parse CSV but do not persist');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Health Canada Registry Sync');

        // ── Download CSV ───────────────────────────────────────────────────
        $io->text('Downloading CSV from Health Canada...');

        try {
            $response = $this->httpClient->request('GET', self::HC_CSV_URL, ['timeout' => 30]);
            $csvContent = $response->getContent();
        } catch (\Throwable $e) {
            $io->error('Failed to download CSV: ' . $e->getMessage());
            $this->logger->error('[HC Sync] Download failed', ['error' => $e->getMessage()]);

            return Command::FAILURE;
        }

        // ── Parse CSV ──────────────────────────────────────────────────────
        $rows = $this->parseCsv($csvContent);

        if (count($rows) < 2) {
            $io->error('CSV appears empty or malformed (fewer than 2 rows).');

            return Command::FAILURE;
        }

        $header = array_shift($rows); // remove header row
        $io->text(sprintf('Downloaded %d producer records.', count($rows)));

        if ($dryRun) {
            $io->note('Dry-run mode — no changes persisted.');
            $io->success(sprintf('Would upsert %d records.', count($rows)));

            return Command::SUCCESS;
        }

        // ── Upsert into DB ─────────────────────────────────────────────────
        $repo = $this->em->getRepository(HealthCanadaLicensedProducer::class);

        $counts = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($rows as $index => $row) {
            $licenseNumber = strtoupper(trim($row[self::COL_LICENSE_NUMBER] ?? ''));

            if ($licenseNumber === '') {
                $counts['skipped']++;
                continue;
            }

            $producer = $repo->findOneBy(['licenseNumber' => $licenseNumber]);
            $isNew    = $producer === null;

            if ($isNew) {
                $producer = new HealthCanadaLicensedProducer();
                $producer->setLicenseNumber($licenseNumber);
            } else {
                $producer->touch();
            }

            $producer
                ->setCompanyName($row[self::COL_COMPANY_NAME] ?? '')
                ->setLicenseType($row[self::COL_LICENSE_TYPE] ?? '')
                ->setStatus($this->normalizeStatus($row[self::COL_STATUS] ?? ''))
                ->setProvince($row[self::COL_PROVINCE] ?? '')
                ->setIssuedAt($this->parseDate($row[self::COL_ISSUED_AT] ?? ''))
                ->setExpiresAt($this->parseDate($row[self::COL_EXPIRES_AT] ?? ''));

            $this->em->persist($producer);

            $isNew ? $counts['inserted']++ : $counts['updated']++;

            // Flush every 200 records to avoid memory pressure
            if (($index + 1) % 200 === 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        $this->em->flush();

        $io->success(sprintf(
            'Sync complete — inserted: %d, updated: %d, skipped: %d.',
            $counts['inserted'],
            $counts['updated'],
            $counts['skipped'],
        ));

        $this->logger->info('[HC Sync] Completed', $counts);

        return Command::SUCCESS;
    }

    /** @return list<list<string>> */
    private function parseCsv(string $content): array
    {
        $lines = [];
        $handle = fopen('php://memory', 'r+');

        if ($handle === false) {
            return [];
        }

        fwrite($handle, $content);
        rewind($handle);

        while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
            $lines[] = $row;
        }

        fclose($handle);

        return $lines;
    }

    private function normalizeStatus(string $raw): string
    {
        return match (strtolower(trim($raw))) {
            'authorized', 'active', 'licensed' => 'active',
            'suspended'                         => 'suspended',
            'expired'                           => 'expired',
            'cancelled', 'revoked'              => 'cancelled',
            default                             => strtolower(trim($raw)) ?: 'unknown',
        };
    }

    private function parseDate(string $raw): ?\DateTimeImmutable
    {
        $raw = trim($raw);

        if ($raw === '' || $raw === 'N/A' || $raw === '-') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'Y/m/d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $raw);

            if ($date !== false) {
                return $date->setTime(0, 0);
            }
        }

        return null;
    }
}
