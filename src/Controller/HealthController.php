<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/api/health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        try {
            $this->em->getConnection()->executeQuery('SELECT 1');
            $db = 'ok';
        } catch (\Throwable) {
            $db = 'error';
        }

        $status = $db === 'ok' ? 'ok' : 'degraded';
        $httpCode = $db === 'ok' ? 200 : 503;

        return $this->json([
            'status' => $status,
            'db' => $db,
            'env' => $this->getParameter('kernel.environment'),
            'version' => '1.0.0',
        ], $httpCode);
    }
}
