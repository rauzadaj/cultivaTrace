<?php

namespace App\Infrastructure\Http\Controller;

use App\Application\Cultivation\Analytics\CropCycleAnalyticsService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class CropCycleAnalyticsController
{
    public function __construct(
        private CropCycleAnalyticsService $analyticsService,
    ) {
    }

    #[Route('/api/analytics/cycle-average', name: 'api_analytics_cycle_average', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $geneticId = $request->query->getString('geneticId') ?: null;
        $averages = $this->analyticsService->getAverageCycleDurations($geneticId);

        return new JsonResponse([
            'data' => array_map(static fn ($average) => $average->toArray(), $averages),
        ]);
    }
}
