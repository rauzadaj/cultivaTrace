<?php

namespace App\Infrastructure\Http\Controller;

use App\Entity\Organization;
use App\Entity\User;
use App\Service\Auth\EmailVerificationService;
use App\Service\Auth\PasswordPolicy;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class RegisterUserController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private RateLimiterFactory $registerRateLimiter,
        private PasswordPolicy $passwordPolicy,
        private EmailVerificationService $emailVerificationService,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $limit = $this->registerRateLimiter
            ->create($request->getClientIp() ?? 'unknown')
            ->consume();

        if (!$limit->isAccepted()) {
            return new JsonResponse([
                'error' => 'Too many requests, please try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $payload = $this->resolvePayload($request);
        $email = $this->resolveEmail($payload);
        $password = $this->resolvePassword($payload);
        $organization = $this->resolveOrganization($payload);

        /** @var User|null $existingUser */
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($existingUser instanceof User) {
            return $this->genericRegistrationResponse();
        }

        $user = (new User())
            ->setEmail($email)
            ->setOrganization($organization)
            ->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $verificationToken = $this->emailVerificationService->issueToken($user);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->sendVerificationEmail($user, $verificationToken);

        return $this->genericRegistrationResponse();
    }

    /** @return array<string, mixed> */
    private function resolvePayload(Request $request): array
    {
        try {
            return [] === $request->request->all()
                ? ($request->getContent() !== '' ? $request->toArray() : [])
                : $request->request->all();
        } catch (JsonException $exception) {
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

        $this->passwordPolicy->assertValid($password);

        return $password;
    }

    /** @param array<string, mixed> $payload */
    private function resolveOrganization(array $payload): Organization
    {
        $organizationId = trim((string) ($payload['organizationId'] ?? ''));

        if ($organizationId === '') {
            throw new InvalidArgumentException('A valid organization is required.');
        }

        /** @var Organization|null $organization */
        $organization = $this->entityManager->getRepository(Organization::class)->find($organizationId);

        if (!$organization instanceof Organization || $organization->isSuspended()) {
            throw new InvalidArgumentException('A valid organization is required.');
        }

        return $organization;
    }

    private function sendVerificationEmail(User $user, string $verificationToken): void
    {
        $verificationUrl = $this->urlGenerator->generate('api_auth_verify_email', [
            'email' => $user->getEmail(),
            'token' => $verificationToken,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->mailer->send((new Email())
            ->to((string) $user->getEmail())
            ->subject('Verify your CultivaTrace email')
            ->text(sprintf("Verify your email within 24 hours: %s", $verificationUrl)));
    }

    private function genericRegistrationResponse(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'If the registration can be completed, a verification email will be sent shortly.',
        ], Response::HTTP_OK);
    }
}
