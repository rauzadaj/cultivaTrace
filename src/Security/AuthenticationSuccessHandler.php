<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Enum\UserAccountStatus;
use App\Service\Auth\LoginAttemptService;
use App\Service\Auth\RefreshTokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final readonly class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private JWTTokenManagerInterface $jwtTokenManager,
        private RefreshTokenService $refreshTokenService,
        private LoginAttemptService $loginAttemptService,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->hasOrganization()) {
            return new JsonResponse(['error' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        $organization = $user->getOrganization();
        if ($organization->isSuspended() || $user->getAccountStatus() !== UserAccountStatus::ACTIVE) {
            return new JsonResponse(['error' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        $this->loginAttemptService->clear($user->getEmail() ?? '');
        $issued = $this->refreshTokenService->issue($user);
        $refreshToken = $issued['refreshToken'];

        $accessToken = $this->jwtTokenManager->createFromPayload($user, [
            'tenantId' => (string) $organization->getId(),
            'roles' => $user->getRoles(),
            'sid' => $refreshToken->getId(),
            'iat' => time(),
        ]);

        return new JsonResponse([
            'token' => $accessToken,
            'refreshToken' => $issued['plainToken'],
            'expiresIn' => 900,
        ], Response::HTTP_OK);
    }
}
