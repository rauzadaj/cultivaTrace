<?php

namespace App\Tests\Compliance\CTLS;

use App\Compliance\CTLS\Normalization\CtlsValueNormalizer;
use PHPUnit\Framework\TestCase;

final class CtlsValueNormalizerTest extends TestCase
{
    public function testItNormalizesTheReportingPeriod(): void
    {
        $normalizer = new CtlsValueNormalizer();

        self::assertSame([
            'year' => '2026',
            'month' => '03',
        ], $normalizer->normalizePeriod('2026-03'));
    }

    public function testItRejectsAnInvalidReportingPeriod(): void
    {
        $normalizer = new CtlsValueNormalizer();

        $this->expectException(\InvalidArgumentException::class);
        $normalizer->normalizePeriod('2026/03');
    }

    public function testItDetectsBlankRows(): void
    {
        $normalizer = new CtlsValueNormalizer();

        self::assertTrue($normalizer->isBlankRow([
            'Plant ID' => '',
            'Notes' => '  ',
        ]));
    }
}
