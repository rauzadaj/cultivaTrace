<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Entity\User;
use App\Service\Auth\PasswordPolicy;
use App\Service\Auth\RefreshTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final readonly class ChangePasswordController
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private PasswordPolicy $passwordPolicy,
        private RefreshTokenService $refreshTokenService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/auth/change-password', name: 'api_auth_change_password', methods: ['POST'])]
    public function __invoke(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $this->resolvePayload($request);
        $currentPassword = (string) ($payload['currentPassword'] ?? '');
        $newPassword = (string) ($payload['newPassword'] ?? '');

        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            return new JsonResponse(['error' => 'Current password is invalid.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->passwordPolicy->assertValid($newPassword);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $this->refreshTokenService->revokeAllForUser($user);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Password updated successfully.'], Response::HTTP_OK);
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
