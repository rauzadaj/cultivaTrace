<?php

namespace App\Compliance\CTLS\Export;

final readonly class CtlsExportResult
{
    public function __construct(
        public ?string $csv,
        public CtlsExportReport $report,
    ) {
    }
}
