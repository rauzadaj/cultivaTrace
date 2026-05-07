<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Sensor;
use App\Repository\SensorReadingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/sensors/{id}/readings?period=30d
 *
 * Retourne l'historique agrégé d'un capteur.
 * Periods disponibles : 7d | 30d | 90d | 365d
 */
#[Route('/api/sensors/{id}/readings', methods: ['GET'])]
class SensorHistoryController extends AbstractController
{
    public function __construct(
        private readonly SensorReadingRepository $readings,
    ) {}

    public function __invoke(Sensor $sensor, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ORG_USER');
        $this->denyAccessUnlessGranted('TENANT_ACCESS', $sensor);

        $period = $request->query->get('period', '30d');

        if (!in_array($period, ['7d', '30d', '90d', '365d'], true)) {
            return $this->json(
                ['error' => 'period invalide. Valeurs : 7d, 30d, 90d, 365d'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $history = $this->readings->findHistory(
            (string) $sensor->getId(),
            $period
        );

        return $this->json([
            'sensorId' => (string) $sensor->getId(),
            'type'     => $sensor->getType(),
            'period'   => $period,
            'data'     => $history,
            'count'    => count($history),
        ]);
    }
}
