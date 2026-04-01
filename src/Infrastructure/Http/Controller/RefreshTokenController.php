<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Entity\User;
use App\Enum\UserAccountStatus;
use App\Service\Auth\RefreshTokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class RefreshTokenController
{
    public function __construct(
        private RefreshTokenService $refreshTokenService,
        private JWTTokenManagerInterface $jwtTokenManager,
    ) {
    }

    #[Route('/api/auth/token/refresh', name: 'api_auth_token_refresh', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $this->resolvePayload($request);
        $plainToken = trim((string) ($payload['refreshToken'] ?? ''));

        if ($plainToken === '') {
            return new JsonResponse(['error' => 'Invalid refresh token.'], Response::HTTP_UNAUTHORIZED);
        }

        $rotated = $this->refreshTokenService->rotate($plainToken);

        if ($rotated === null) {
            return new JsonResponse(['error' => 'Invalid refresh token.'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var User $user */
        $user = $rotated['refreshToken']->getUser();
        $organization = $user->getOrganization();

        if ($organization === null || $organization->isSuspended() || $user->getAccountStatus() !== UserAccountStatus::ACTIVE) {
            $this->refreshTokenService->revoke($rotated['refreshToken']);

            return new JsonResponse(['error' => 'Invalid refresh token.'], Response::HTTP_UNAUTHORIZED);
        }

        $accessToken = $this->jwtTokenManager->createFromPayload($user, [
            'tenantId' => (string) $organization->getId(),
            'roles' => $user->getRoles(),
            'sid' => $rotated['refreshToken']->getId(),
            'iat' => time(),
        ]);

        return new JsonResponse([
            'token' => $accessToken,
            'refreshToken' => $rotated['plainToken'],
            'expiresIn' => 900,
        ], Response::HTTP_OK);
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
