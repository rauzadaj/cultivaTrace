<?php

declare(strict_types=1);

namespace App\Tests\Service\Export;

use App\Service\Export\SpreadsheetCell;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpreadsheetCellTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function cells(): iterable
    {
        foreach (['=1+1', '+SUM(1,2)', '-1+1', '@SUM(1,2)', '  =1', "\ttext", "\rtext", "\ntext", "\x00=1"] as $value) {
            yield bin2hex($value) => [$value, "'" . $value];
        }
        foreach (['', 'Normal, "quoted" text', 'Échantillon', 'Room-1', '10.25', '2026-09-14', 'text = 1', "'already text"] as $value) {
            yield 'safe-' . $value => [$value, $value];
        }
    }

    #[DataProvider('cells')]
    public function testTextRemainsLiteralInSpreadsheetCsv(string $input, string $expected): void
    {
        $cell = SpreadsheetCell::text($input);
        self::assertSame($expected, $cell);
        self::assertSame($cell, SpreadsheetCell::text($cell));
    }
}
