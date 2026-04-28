<?php

declare(strict_types=1);

namespace App\Security;

use App\Service\Auth\LoginAttemptService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

final readonly class AuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private LoginAttemptService $loginAttemptService,
    ) {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $email = $this->extractEmail($request);

        if ($email !== null) {
            $this->loginAttemptService->recordFailure($email);
        }

        return new JsonResponse([
            'error' => 'Invalid credentials.',
        ], Response::HTTP_UNAUTHORIZED);
    }

    private function extractEmail(Request $request): ?string
    {
        try {
            $payload = $request->getContent() !== '' ? $request->toArray() : [];
        } catch (\JsonException) {
            return null;
        }

        $email = trim((string) ($payload['email'] ?? ''));

        return $email !== '' ? mb_strtolower($email) : null;
    }
}
