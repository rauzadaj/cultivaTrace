<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Entity\User;
use App\Service\Auth\RefreshTokenService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final readonly class LogoutController
{
    public function __construct(
        private RefreshTokenService $refreshTokenService,
    ) {
    }

    #[Route('/api/auth/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function __invoke(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user instanceof User) {
            $this->refreshTokenService->revokeAllForUser($user);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
