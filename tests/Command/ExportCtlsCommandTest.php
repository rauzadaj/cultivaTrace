<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\ExportCtlsCommand;
use App\Compliance\CTLS\Export\CtlsExportService;
use App\Compliance\CTLS\Export\CtlsCsvExporter;
use App\Compliance\CTLS\Export\CtlsRowBuilder;
use App\Compliance\CTLS\Mapping\CtlsFieldMap;
use App\Compliance\CTLS\Normalization\CtlsValueNormalizer;
use App\Compliance\CTLS\OfficialTemplate\CtlsTemplateProvider;
use App\Compliance\CTLS\Source\InternalPlantCsvReader;
use App\Compliance\CTLS\Validation\CtlsExportValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ExportCtlsCommandTest extends TestCase
{
    private function buildCommand(): ExportCtlsCommand
    {
        $provider = new CtlsTemplateProvider(dirname(__DIR__, 2));
        $fieldMap = new CtlsFieldMap();
        $normalizer = new CtlsValueNormalizer();

        return new ExportCtlsCommand(new CtlsExportService(
            new InternalPlantCsvReader($fieldMap),
            new CtlsRowBuilder($provider, $fieldMap, $normalizer),
            new CtlsExportValidator($provider),
            new CtlsCsvExporter($provider),
        ));
    }

    public function testItReturnsFailureForNonConvertibleActivity(): void
    {
        $commandTester = new CommandTester($this->buildCommand());
        $outputFile = tempnam(sys_get_temp_dir(), 'ctls-output-');
        $reportFile = tempnam(sys_get_temp_dir(), 'ctls-report-');

        self::assertNotFalse($outputFile);
        self::assertNotFalse($reportFile);

        self::assertSame(Command::FAILURE, $commandTester->execute([
            '--input' => dirname(__DIR__) . '/Fixtures/compliance/internal-current-export.csv',
            '--output' => $outputFile,
            '--period' => '2026-03',
            '--licence-id' => 'LIC-CT-2026-0001',
            '--business-stats' => dirname(__DIR__) . '/Fixtures/compliance/business-stats.json',
            '--report' => $reportFile,
        ]));

        self::assertStringContainsString('CTLS export blocked', $commandTester->getDisplay());
        self::assertJson((string) file_get_contents($reportFile));
    }
}
