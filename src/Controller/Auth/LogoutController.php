<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Service\Auth\RefreshTokenService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class LogoutController
{
    public function __construct(
        private RefreshTokenService $refreshTokenService,
    ) {
    }

    /**
     * Logout endpoint revoking the provided refresh token.
     */
    #[Route('/api/auth/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $payload = $this->resolvePayload($request);
        $plainToken = trim((string) ($payload['refresh_token'] ?? $payload['refreshToken'] ?? ''));

        if ($plainToken === '') {
            return new JsonResponse(['error' => 'Refresh token is required.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->refreshTokenService->revoke($plainToken);
        } catch (AccessDeniedException) {
            return new JsonResponse(['error' => 'Refresh token cannot be revoked.'], Response::HTTP_FORBIDDEN);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePayload(Request $request): array
    {
        try {
            return $request->getContent() !== '' ? $request->toArray() : [];
        } catch (\JsonException) {
            return [];
        }
    }
}
