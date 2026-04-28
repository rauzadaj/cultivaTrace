<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Entity\User;
use App\Service\Auth\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class VerifyEmailController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmailVerificationService $emailVerificationService,
    ) {
    }

    #[Route('/api/auth/verify-email', name: 'api_auth_verify_email', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $email = mb_strtolower(trim((string) $request->query->get('email', '')));
        $token = trim((string) $request->query->get('token', ''));

        if ($email === '' || $token === '') {
            return new JsonResponse(['error' => 'Invalid verification link.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user instanceof User || !$this->emailVerificationService->verify($user, $token)) {
            return new JsonResponse(['error' => 'Invalid verification link.'], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Email verified successfully.'], Response::HTTP_OK);
    }
}
