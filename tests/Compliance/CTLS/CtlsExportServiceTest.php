<?php

namespace App\Tests\Compliance\CTLS;

use App\Compliance\CTLS\Export\CtlsCsvExporter;
use App\Compliance\CTLS\Export\CtlsExportService;
use App\Compliance\CTLS\Export\CtlsRowBuilder;
use App\Compliance\CTLS\Mapping\CtlsFieldMap;
use App\Compliance\CTLS\Normalization\CtlsValueNormalizer;
use App\Compliance\CTLS\OfficialTemplate\CtlsTemplateProvider;
use App\Compliance\CTLS\Source\InternalPlantCsvReader;
use App\Compliance\CTLS\Validation\CtlsExportValidator;
use PHPUnit\Framework\TestCase;

final class CtlsExportServiceTest extends TestCase
{
    private function buildService(): CtlsExportService
    {
        $provider = new CtlsTemplateProvider(dirname(__DIR__, 3));
        $fieldMap = new CtlsFieldMap();
        $normalizer = new CtlsValueNormalizer();

        return new CtlsExportService(
            new InternalPlantCsvReader($fieldMap),
            new CtlsRowBuilder($provider, $fieldMap, $normalizer),
            new CtlsExportValidator($provider),
            new CtlsCsvExporter($provider),
        );
    }

    public function testItExportsANoActivityMonthWhenMandatoryMetadataIsProvided(): void
    {
        $service = $this->buildService();
        $result = $service->exportFromCsv(
            dirname(__DIR__, 2) . '/Fixtures/compliance/internal-no-activity.csv',
            '2026-03',
            'LIC-CT-2026-0001',
            [
                'Management Employees' => 1,
                'Administrative Employees' => 1,
                'Sales Employees' => 0,
                'Production Employees' => 2,
                'Other Employees' => 0,
                'Licensed growing area' => 120.50,
                'Licensed processing area' => 45.00,
                'Total building(s) area' => 210.75,
                'Licensed outdoor growing area' => 0.00,
            ],
        );

        self::assertFalse($result->report->hasBlockingErrors());
        self::assertNotNull($result->csv);
        self::assertStringStartsWith("\xEF\xBB\xBF", (string) $result->csv);
        self::assertStringContainsString('LIC-CT-2026-0001', (string) $result->csv);
    }

    public function testItFailsLoudlyWhenPlantLevelActivityCannotBeMapped(): void
    {
        $service = $this->buildService();
        $result = $service->exportFromCsv(
            dirname(__DIR__, 2) . '/Fixtures/compliance/internal-current-export.csv',
            '2026-03',
            'LIC-CT-2026-0001',
            [
                'Management Employees' => 1,
                'Administrative Employees' => 1,
                'Sales Employees' => 0,
                'Production Employees' => 2,
                'Other Employees' => 0,
                'Licensed growing area' => 120.50,
                'Licensed processing area' => 45.00,
                'Total building(s) area' => 210.75,
                'Licensed outdoor growing area' => 0.00,
            ],
        );

        self::assertTrue($result->report->hasBlockingErrors());
        self::assertNull($result->csv);
        self::assertStringContainsString(
            'Plant ID',
            json_encode($result->report->toArray(), JSON_THROW_ON_ERROR)
        );
    }
}
