<?php

namespace App\Compliance\CTLS\Export;

use App\Compliance\CTLS\Mapping\CtlsFieldMap;
use App\Compliance\CTLS\Normalization\CtlsValueNormalizer;
use App\Compliance\CTLS\OfficialTemplate\CtlsTemplateProvider;

final class CtlsRowBuilder
{
    public function __construct(
        private readonly CtlsTemplateProvider $templateProvider,
        private readonly CtlsFieldMap $fieldMap,
        private readonly CtlsValueNormalizer $normalizer,
    ) {
    }

    /**
     * @param list<array<string, string>> $sourceRows
     * @param array<string, mixed> $businessStats
     * @return array<string, string>
     */
    public function build(
        array $sourceRows,
        string $period,
        ?string $licenceId,
        array $businessStats,
        CtlsExportReport $report,
    ): array {
        $headers = $this->templateProvider->getHeaders();
        $row = array_fill_keys($headers, '');
        $report->setSourceLineCount(count($sourceRows));

        $normalizedPeriod = $this->normalizer->normalizePeriod($period);
        $row['Reporting Period Year (####)'] = $normalizedPeriod['year'];
        $row['Reporting Period Month (##)'] = $normalizedPeriod['month'];
        $report->addMapping('Report Period', 'Reporting Period Year (####)', 'Derived from the command period.');
        $report->addMapping('Report Period', 'Reporting Period Month (##)', 'Derived from the command period.');

        if ($licenceId === null || trim($licenceId) === '') {
            $report->addError(
                'metadata',
                'Licence ID',
                'Licence ID',
                'The official CTLS template requires the federal licence identifier.',
                'Pass --licence-id with the exact CTLS licence identifier used in the portal.',
            );
        } else {
            $row['Licence ID'] = trim($licenceId);
            $report->addMapping('metadata', 'Licence ID', 'Provided explicitly by the operator.');
        }

        foreach ($this->templateProvider->getBusinessStatisticsHeaders() as $header) {
            try {
                $row[$header] = str_contains(strtolower($header), 'area')
                    ? $this->normalizer->normalizeRequiredDecimal($businessStats[$header] ?? null, $header)
                    : $this->normalizer->normalizeRequiredInteger($businessStats[$header] ?? null, $header);

                $report->addMapping('business-stats', $header, 'Provided explicitly by the operator.');
            } catch (\InvalidArgumentException $exception) {
                $report->addError(
                    'metadata',
                    $header,
                    $header,
                    $exception->getMessage(),
                    'Provide the business statistics JSON with all mandatory employee counts and licensed areas.',
                );
            }
        }

        foreach ($this->fieldMap->getUnsupportedSourceFieldReasons() as $sourceField => $reason) {
            $report->addIgnoredField($sourceField, $reason);
        }

        foreach ($sourceRows as $index => $sourceRow) {
            if ($this->normalizer->isBlankRow($sourceRow)) {
                continue;
            }

            foreach ($sourceRow as $sourceColumn => $value) {
                if (trim($value) === '') {
                    continue;
                }

                if ($sourceColumn === 'Report Period' && $value !== $period) {
                    $report->addWarning(
                        'Report Period',
                        sprintf('Source row %d uses "%s" while the command period is "%s". The command period wins.', $index + 2, $value, $period),
                    );
                    continue;
                }

                if ($sourceColumn === 'Report Period') {
                    continue;
                }

                $report->addError(
                    $index + 2,
                    $sourceColumn,
                    'CTLS aggregate report row',
                    sprintf(
                        'Current internal field "%s" contains data but CultivaTrace does not yet have the regulatory movement mapping required to place it into the official CTLS template.',
                        $sourceColumn
                    ),
                    'Add explicit regulatory mapping data and monthly aggregate source tables before attempting a CTLS export with activity.',
                );
            }
        }

        return $row;
    }
}
