<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\MercureService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class MercureTokenController extends AbstractController
{
    public function __construct(
        private readonly MercureService $mercureService,
        #[Autowire(service: 'limiter.api_mercure_token')]
        private readonly RateLimiterFactory $mercureTokenLimiter,
    ) {
    }

    #[Route('/api/mercure/token', name: 'api_mercure_token', methods: ['GET'])]
    public function __invoke(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user instanceof User || !$user->hasOrganization()) {
            return $this->json(['message' => 'Utilisateur non authentifie'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $limiter = $this->mercureTokenLimiter->create($user->getUserIdentifier());
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->json(['message' => 'Too many requests.'], JsonResponse::HTTP_TOO_MANY_REQUESTS);
        }

        return $this->json(
            $this->mercureService->createSubscriptionToken((string) $user->getOrganization()->getId())
        );
    }
}
