<?php

namespace App\Compliance\CTLS\Normalization;

final class CtlsValueNormalizer
{
    /**
     * @return array{year: string, month: string}
     */
    public function normalizePeriod(string $period): array
    {
        $period = trim($period);
        if (!preg_match('/^(?<year>\d{4})-(?<month>0[1-9]|1[0-2])$/', $period, $matches)) {
            throw new \InvalidArgumentException(sprintf('Invalid reporting period "%s". Expected YYYY-MM.', $period));
        }

        return [
            'year' => $matches['year'],
            'month' => $matches['month'],
        ];
    }

    public function normalizeRequiredInteger(mixed $value, string $field): string
    {
        $value = is_string($value) ? trim($value) : $value;
        if ($value === '' || $value === null) {
            throw new \InvalidArgumentException(sprintf('Missing required integer value for "%s".', $field));
        }

        if (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            throw new \InvalidArgumentException(sprintf('Invalid non-negative integer value for "%s".', $field));
        }

        return (string) (int) $value;
    }

    public function normalizeRequiredDecimal(mixed $value, string $field): string
    {
        $value = is_string($value) ? trim($value) : $value;
        if ($value === '' || $value === null) {
            throw new \InvalidArgumentException(sprintf('Missing required decimal value for "%s".', $field));
        }

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid decimal value for "%s".', $field));
        }

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @param array<string, mixed> $row
     */
    public function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
