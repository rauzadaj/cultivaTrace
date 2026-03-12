<?php

namespace App\Infrastructure\Http\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final readonly class RegisterUserController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $this->resolvePayload($request);
        $email = $this->resolveEmail($payload);
        $password = $this->resolvePassword($payload);

        /** @var User|null $existingUser */
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($existingUser instanceof User) {
            throw new ConflictHttpException('An account already exists for this email address.');
        }

        $user = (new User())
            ->setEmail($email)
            ->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
        ], Response::HTTP_CREATED);
    }

    /** @return array<string, mixed> */
    private function resolvePayload(Request $request): array
    {
        try {
            return [] === $request->request->all()
                ? ($request->getContent() !== '' ? $request->toArray() : [])
                : $request->request->all();
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException('Invalid JSON payload.', previous: $exception);
        }
    }

    /** @param array<string, mixed> $payload */
    private function resolveEmail(array $payload): string
    {
        $email = strtolower(trim((string) ($payload['email'] ?? '')));

        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email address is required.');
        }

        return $email;
    }

    /** @param array<string, mixed> $payload */
    private function resolvePassword(array $payload): string
    {
        $password = (string) ($payload['password'] ?? '');

        if (mb_strlen($password) < 8) {
            throw new InvalidArgumentException('Password must contain at least 8 characters.');
        }

        return $password;
    }
}
