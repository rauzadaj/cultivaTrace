<?php

namespace App\Application\Cultivation\Analytics;

use App\Application\Cultivation\Analytics\ReadModel\GeneticCycleAverage;

interface CropCycleAnalyticsGateway
{
    /** @return list<GeneticCycleAverage> */
    public function getAverageCycleDurations(?string $geneticId = null): array;
}
