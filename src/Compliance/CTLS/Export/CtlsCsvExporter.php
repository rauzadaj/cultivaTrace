<?php

namespace App\Compliance\CTLS\Export;

use App\Compliance\CTLS\OfficialTemplate\CtlsTemplateProvider;

final class CtlsCsvExporter
{
    public function __construct(
        private readonly CtlsTemplateProvider $templateProvider,
    ) {
    }

    /**
     * @param array<string, string> $row
     */
    public function export(array $row): string
    {
        $handle = fopen('php://temp', 'w+');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open temporary stream for CTLS export.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $this->templateProvider->getHeaders(), escape: '');
        fputcsv($handle, array_map(static fn (string $header): string => $row[$header] ?? '', $this->templateProvider->getHeaders()), escape: '');
        rewind($handle);

        $csv = stream_get_contents($handle);
        fclose($handle);

        if ($csv === false) {
            throw new \RuntimeException('Unable to read generated CTLS CSV payload.');
        }

        return $csv;
    }
}
