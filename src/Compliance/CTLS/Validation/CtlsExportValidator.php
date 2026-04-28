<?php

namespace App\Compliance\CTLS\Validation;

use App\Compliance\CTLS\Export\CtlsExportReport;
use App\Compliance\CTLS\OfficialTemplate\CtlsTemplateProvider;

final class CtlsExportValidator
{
    public function __construct(
        private readonly CtlsTemplateProvider $templateProvider,
    ) {
    }

    /**
     * @param array<string, string> $row
     */
    public function validate(array $row, CtlsExportReport $report): void
    {
        $headers = $this->templateProvider->getHeaders();
        $rowHeaders = array_keys($row);

        if ($rowHeaders !== $headers) {
            $report->addError(
                'output',
                'headers',
                'template',
                'Generated headers do not exactly match the official CTLS template snapshot.',
                'Regenerate the output row using the exact header order returned by CtlsTemplateProvider.',
            );
        }

        foreach ($this->templateProvider->getRequiredHeaders() as $requiredHeader) {
            if (!array_key_exists($requiredHeader, $row) || trim($row[$requiredHeader]) === '') {
                $report->addError(
                    'output',
                    $requiredHeader,
                    $requiredHeader,
                    'A required CTLS field is blank.',
                    'Populate every required field before exporting the final CSV.',
                );
            }
        }

        if (($year = $row['Reporting Period Year (####)'] ?? '') !== '' && !preg_match('/^\d{4}$/', $year)) {
            $report->addError('output', 'Reporting Period Year (####)', 'Reporting Period Year (####)', 'Invalid year format.', 'Use the four-digit YYYY format.');
        }

        if (($month = $row['Reporting Period Month (##)'] ?? '') !== '' && !preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
            $report->addError('output', 'Reporting Period Month (##)', 'Reporting Period Month (##)', 'Invalid month format.', 'Use the two-digit MM format.');
        }

        foreach ($this->templateProvider->getBusinessStatisticsHeaders() as $header) {
            $value = $row[$header] ?? '';
            if ($value === '') {
                continue;
            }

            $pattern = str_contains(strtolower($header), 'area') ? '/^\d+\.\d{2}$/' : '/^\d+$/';
            if (!preg_match($pattern, $value)) {
                $report->addError(
                    'output',
                    $header,
                    $header,
                    'Business statistics use an invalid numeric format.',
                    'Use a non-negative integer for employee counts and a decimal with two digits for areas.',
                );
            }
        }
    }
}
