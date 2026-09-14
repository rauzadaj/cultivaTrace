<?php

declare(strict_types=1);

namespace App\Service\Export;

/** Escapes untrusted text for internal spreadsheet exports, never regulatory mappings. */
final class SpreadsheetCell
{
    public static function text(string $value): string
    {
        // Quoting a CSV field does not prevent spreadsheet formula interpretation.
        if (preg_match('/^[\s\x00-\x1F]*[=+@-]|^[\t\r\n]/u', $value) === 1) {
            return "'" . $value;
        }

        return $value;
    }
}
