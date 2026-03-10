<?php

namespace App\Application\Cultivation\Analytics;

use App\Application\Cultivation\Analytics\ReadModel\GeneticCycleAverage;

final readonly class CropCycleAnalyticsService
{
    public function __construct(
        private CropCycleAnalyticsGateway $analyticsGateway,
    ) {
    }

    /** @return list<GeneticCycleAverage> */
    public function getAverageCycleDurations(?string $geneticId = null): array
    {
        return $this->analyticsGateway->getAverageCycleDurations($geneticId);
    }
}
