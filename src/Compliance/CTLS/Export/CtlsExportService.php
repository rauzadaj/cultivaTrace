<?php

namespace App\Compliance\CTLS\Export;

use App\Compliance\CTLS\Source\InternalPlantCsvReader;
use App\Compliance\CTLS\Validation\CtlsExportValidator;

final class CtlsExportService
{
    public function __construct(
        private readonly InternalPlantCsvReader $sourceReader,
        private readonly CtlsRowBuilder $rowBuilder,
        private readonly CtlsExportValidator $validator,
        private readonly CtlsCsvExporter $csvExporter,
    ) {
    }

    /**
     * @param array<string, mixed> $businessStats
     */
    public function exportFromCsv(
        string $inputPath,
        string $period,
        ?string $licenceId,
        array $businessStats,
    ): CtlsExportResult {
        $rows = $this->sourceReader->read($inputPath);
        $report = new CtlsExportReport();
        $row = $this->rowBuilder->build($rows, $period, $licenceId, $businessStats, $report);
        $this->validator->validate($row, $report);

        if ($report->hasBlockingErrors()) {
            $report->setExportedRowCount(0);

            return new CtlsExportResult(null, $report);
        }

        $report->setExportedRowCount(1);

        return new CtlsExportResult($this->csvExporter->export($row), $report);
    }
}
