<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\MercureService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class MercureTokenController extends AbstractController
{
    public function __construct(
        private readonly MercureService $mercureService,
    ) {
    }

    #[Route('/api/mercure/token', name: 'api_mercure_token', methods: ['GET'])]
    public function __invoke(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user instanceof User || !$user->hasOrganization()) {
            return $this->json(['message' => 'Utilisateur non authentifie'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return $this->json(
            $this->mercureService->createSubscriptionToken((string) $user->getOrganization()->getId())
        );
    }
}
