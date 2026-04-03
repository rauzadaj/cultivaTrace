<?php

namespace App\Compliance\CTLS\Source;

use App\Compliance\CTLS\Mapping\CtlsFieldMap;

final class InternalPlantCsvReader
{
    public function __construct(
        private readonly CtlsFieldMap $fieldMap,
    ) {
    }

    /**
     * @return list<array<string, string>>
     */
    public function read(string $path): array
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(sprintf('Input CSV "%s" does not exist.', $path));
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Unable to open input CSV "%s".', $path));
        }

        try {
            $header = fgetcsv($handle, escape: '');
            if ($header === false) {
                throw new \RuntimeException('The input CSV is empty.');
            }

            $normalizedHeader = $this->normalizeHeader($header);
            if ($normalizedHeader !== $this->fieldMap->getExpectedSourceHeaders()) {
                throw new \RuntimeException('The input CSV does not match the expected internal plant export schema.');
            }

            $rows = [];
            while (($data = fgetcsv($handle, escape: '')) !== false) {
                $data = array_map(static fn (?string $value): string => trim((string) $value), $data);
                $data = array_pad($data, count($normalizedHeader), '');
                $rows[] = array_combine($normalizedHeader, $data);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param list<string|null> $header
     * @return string[]
     */
    private function normalizeHeader(array $header): array
    {
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        }

        return array_map(static fn (?string $value): string => trim((string) $value), $header);
    }
}
