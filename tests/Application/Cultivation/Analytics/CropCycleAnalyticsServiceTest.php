<?php

namespace App\Tests\Application\Cultivation\Analytics;

use App\Application\Cultivation\Analytics\CropCycleAnalyticsGateway;
use App\Application\Cultivation\Analytics\CropCycleAnalyticsService;
use App\Application\Cultivation\Analytics\ReadModel\GeneticCycleAverage;
use PHPUnit\Framework\TestCase;

final class CropCycleAnalyticsServiceTest extends TestCase
{
    public function testItReturnsAverageCycleDurationsByGenetic(): void
    {
        $gateway = new class() implements CropCycleAnalyticsGateway {
            public function getAverageCycleDurations(?string $geneticId = null): array
            {
                return [
                    new GeneticCycleAverage('01ARZ3NDEKTSV4RRFFQ69G5FAV', 'OGK-01', 'OG Kush', 4, 73.5),
                ];
            }
        };

        $service = new CropCycleAnalyticsService($gateway);
        $averages = $service->getAverageCycleDurations();

        self::assertCount(1, $averages);
        self::assertSame('OGK-01', $averages[0]->geneticCode);
        self::assertSame(73.5, $averages[0]->averageCycleDays);
    }
}
