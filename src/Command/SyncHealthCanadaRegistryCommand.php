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
 * Syncs Health Canada's licensed producers list into the health_canada_registry table.
 *
 * Accepts either an HTML listing page or a direct CSV file — auto-detected.
 * The official page is:
 *   https://www.canada.ca/en/health-canada/services/drugs-medication/cannabis/
 *   industry-licensees-applicants/licensed-cultivators-processors-sellers.html
 *
 * Usage:
 *   php bin/console app:sync-health-canada-registry
 *   php bin/console app:sync-health-canada-registry --url=<page-or-csv-url>
 *   php bin/console app:sync-health-canada-registry --file=/tmp/producers.html
 *   php bin/console app:sync-health-canada-registry --file=/tmp/producers.csv
 */
#[AsCommand(
    name: 'app:sync-health-canada-registry',
    description: 'Sync Health Canada authorized licensed producers list for KYB verification',
)]
final class SyncHealthCanadaRegistryCommand extends Command
{
    private const HC_URL_DEFAULT = 'https://www.canada.ca/en/health-canada/services/drugs-medication/cannabis/industry-licensees-applicants/licensed-cultivators-processors-sellers.html';

    /**
     * Keyword patterns used to identify each column by its header text.
     * First match wins; matching is case-insensitive substring.
     *
     * @var array<string, list<string>>
     */
    private const COLUMN_KEYWORDS = [
        'licenseNumber' => ['license number', 'licence number', 'no. de licen'],
        'companyName'   => ['license holder', 'licence holder', 'company', 'holder', 'name'],
        'licenseType'   => ['license class', 'licence class', 'class', 'type of licen'],
        'status'        => ['status'],
        'province'      => ['province', 'territory'],
        'issuedAt'      => ['issue date', 'date issued', 'issued', 'granted'],
        'expiresAt'     => ['expiry date', 'date expir', 'expir', 'renewal'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $hcUrl = self::HC_URL_DEFAULT,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Parse but do not persist')
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'Health Canada page or CSV URL (env: HC_CSV_URL)')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Load from a local HTML or CSV file instead of downloading');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Health Canada Registry Sync');

        // ── Fetch raw content ──────────────────────────────────────────────
        $localFile = $input->getOption('file');

        if (is_string($localFile) && $localFile !== '') {
            $io->text(sprintf('Reading from local file: %s', $localFile));

            if (!file_exists($localFile) || !is_readable($localFile)) {
                $io->error(sprintf('File not found or not readable: %s', $localFile));
                return Command::FAILURE;
            }

            $content = (string) file_get_contents($localFile);
        } else {
            $url = (string) ($input->getOption('url') ?? getenv('HC_CSV_URL') ?: $this->hcUrl);

            try {
                $content = $this->download($url, $io);
            } catch (\Throwable $e) {
                $io->error([
                    'Download failed: ' . $e->getMessage(),
                    'Tips:',
                    '  --url=<url>   override the URL (or set HC_CSV_URL env var)',
                    '  --file=<path> pass a locally saved HTML or CSV file',
                ]);
                $this->logger->error('[HC Sync] Download failed', ['error' => $e->getMessage()]);
                return Command::FAILURE;
            }
        }

        // ── Parse: auto-detect HTML vs CSV ────────────────────────────────
        if ($this->isHtml($content)) {
            $io->text('HTML page detected — scraping table...');
            [$colMap, $rows] = $this->parseHtmlTable($content);

            if ($colMap === [] || $rows === []) {
                $io->error([
                    'Could not find a licensed-producers table in the HTML.',
                    'Check that the URL points to the Health Canada licensed producers page.',
                ]);
                return Command::FAILURE;
            }

            $io->text(sprintf('Found %d producer rows. Column map: %s', count($rows), json_encode($colMap)));
        } else {
            $io->text('CSV format detected — parsing...');
            [$colMap, $rows] = $this->parseCsvContent($content);

            if ($colMap === [] || count($rows) < 1) {
                $io->error('CSV appears empty or its header does not match expected Health Canada format.');
                return Command::FAILURE;
            }

            $io->text(sprintf('Found %d producer rows.', count($rows)));
        }

        if ($dryRun) {
            $io->note('Dry-run — no changes persisted.');
            $io->success(sprintf('Would upsert %d records.', count($rows)));
            return Command::SUCCESS;
        }

        // ── Upsert ────────────────────────────────────────────────────────
        $counts = $this->upsert($rows, $colMap);

        $io->success(sprintf(
            'Sync complete — inserted: %d, updated: %d, skipped: %d.',
            $counts['inserted'],
            $counts['updated'],
            $counts['skipped'],
        ));
        $this->logger->info('[HC Sync] Completed', $counts);

        return Command::SUCCESS;
    }

    // ── HTTP ──────────────────────────────────────────────────────────────────

    private function download(string $url, SymfonyStyle $io): string
    {
        $io->text(sprintf('Downloading from: %s', $url));

        $response = $this->httpClient->request('GET', $url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; CultivaTrace/1.0)',
                'Accept'     => 'text/html,application/xhtml+xml,text/csv,*/*',
            ],
        ]);

        return $response->getContent();
    }

    // ── HTML scraping ─────────────────────────────────────────────────────────

    private function isHtml(string $content): bool
    {
        $trimmed = ltrim($content);
        return stripos($trimmed, '<!doctype') === 0
            || stripos($trimmed, '<html') === 0
            || stripos($trimmed, '<table') !== false;
    }

    /**
     * Parses an HTML page and returns the first table that looks like a
     * licensed-producers list.
     *
     * @return array{0: array<string,int>, 1: list<list<string>>}
     */
    private function parseHtmlTable(string $html): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        /** @var \DOMNodeList<\DOMElement> $tables */
        $tables = $xpath->query('//table');

        foreach ($tables as $table) {
            // Collect header cells from <thead> or the first <tr>
            $headerNodes = $xpath->query('.//thead//th | .//thead//td', $table);
            if ($headerNodes === false || $headerNodes->length === 0) {
                $headerNodes = $xpath->query('.//tr[1]/th | .//tr[1]/td', $table);
            }

            if ($headerNodes === false || $headerNodes->length === 0) {
                continue;
            }

            $headers = [];
            foreach ($headerNodes as $cell) {
                $headers[] = strtolower(trim((string) $cell->textContent));
            }

            $colMap = $this->mapColumns($headers);

            // Need at least licenseNumber + companyName to be useful
            if (!isset($colMap['licenseNumber'], $colMap['companyName'])) {
                continue;
            }

            // Extract data rows
            $rows      = [];
            $dataNodes = $xpath->query('.//tbody/tr', $table);

            // Fallback: all rows except the first if no tbody
            if ($dataNodes === false || $dataNodes->length === 0) {
                $dataNodes = $xpath->query('.//tr[position()>1]', $table);
            }

            if ($dataNodes === false) {
                continue;
            }

            foreach ($dataNodes as $tr) {
                $cells = $xpath->query('td', $tr);
                if ($cells === false) continue;

                $row = [];
                foreach ($cells as $cell) {
                    $row[] = trim((string) $cell->textContent);
                }

                if (count($row) >= 2) {
                    $rows[] = $row;
                }
            }

            if ($rows !== []) {
                return [$colMap, $rows];
            }
        }

        return [[], []];
    }

    /**
     * Maps column header strings to field names using keyword matching.
     *
     * @param  list<string>         $headers  lowercase header texts
     * @return array<string, int>             field => column index
     */
    private function mapColumns(array $headers): array
    {
        $map = [];

        foreach (self::COLUMN_KEYWORDS as $field => $keywords) {
            foreach ($headers as $idx => $header) {
                foreach ($keywords as $keyword) {
                    if (str_contains($header, $keyword)) {
                        $map[$field] = $idx;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    // ── CSV parsing ───────────────────────────────────────────────────────────

    /**
     * @return array{0: array<string,int>, 1: list<list<string>>}
     */
    private function parseCsvContent(string $content): array
    {
        $handle = fopen('php://memory', 'r+');
        if ($handle === false) return [[], []];

        fwrite($handle, $content);
        rewind($handle);

        $allRows = [];
        while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
            $allRows[] = $row;
        }
        fclose($handle);

        if (count($allRows) < 2) return [[], []];

        $rawHeader = array_shift($allRows);
        $headers   = array_map(static fn($h) => strtolower(trim((string) $h)), $rawHeader);
        $colMap    = $this->mapColumns($headers);

        if (!isset($colMap['licenseNumber'], $colMap['companyName'])) {
            return [[], []];
        }

        return [$colMap, $allRows];
    }

    // ── DB upsert ─────────────────────────────────────────────────────────────

    /**
     * @param list<list<string>>  $rows
     * @param array<string, int>  $colMap
     * @return array{inserted:int, updated:int, skipped:int}
     */
    private function upsert(array $rows, array $colMap): array
    {
        $repo   = $this->em->getRepository(HealthCanadaLicensedProducer::class);
        $counts = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($rows as $index => $row) {
            $licenseNumber = strtoupper(trim($row[$colMap['licenseNumber']] ?? ''));

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
                ->setCompanyName($row[$colMap['companyName']] ?? '')
                ->setLicenseType($row[$colMap['licenseType'] ?? -1] ?? '')
                ->setStatus($this->normalizeStatus($row[$colMap['status'] ?? -1] ?? ''))
                ->setProvince($row[$colMap['province'] ?? -1] ?? '')
                ->setIssuedAt($this->parseDate($row[$colMap['issuedAt'] ?? -1] ?? ''))
                ->setExpiresAt($this->parseDate($row[$colMap['expiresAt'] ?? -1] ?? ''));

            $this->em->persist($producer);

            $isNew ? $counts['inserted']++ : $counts['updated']++;

            if (($index + 1) % 200 === 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        $this->em->flush();

        return $counts;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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

        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'Y/m/d', 'F j, Y', 'M j, Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $raw);
            if ($date !== false) {
                return $date->setTime(0, 0);
            }
        }

        return null;
    }
}
