<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\SubscriptionPlan;
use App\Enum\UserAccountStatus;
use App\Service\Auth\PasswordPolicy;
use App\Service\Auth\RefreshTokenService;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final readonly class RegisterOrganizationController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private RateLimiterFactory $registerRateLimiter,
        private PasswordPolicy $passwordPolicy,
        private StripeService $stripeService,
        private JWTTokenManagerInterface $jwtTokenManager,
        private RefreshTokenService $refreshTokenService,
    ) {
    }

    #[Route('/api/register/organization', name: 'api_register_organization', methods: ['POST'])]
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
        $organizationName = $this->resolveOrganizationName($payload);
        $country = $this->resolveCountry($payload);
        $selectedPlan = $this->resolveSelectedPlan($payload);

        /** @var User|null $existingUser */
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser instanceof User) {
            // Generic response to prevent email enumeration — do not reveal whether email exists
            return new JsonResponse([
                'message' => 'If the registration can be completed, a confirmation email will be sent.',
            ], Response::HTTP_OK);
        }

        /** @var Organization|null $existingOrganization */
        $existingOrganization = $this->entityManager->getRepository(Organization::class)->findOneBy([
            'name' => $organizationName,
        ]);
        if ($existingOrganization instanceof Organization) {
            return new JsonResponse([
                'error' => 'An organization with this name already exists.',
            ], Response::HTTP_CONFLICT);
        }

        $organization = (new Organization())
            ->setName($organizationName)
            ->setCountry($country);

        $user = (new User())
            ->setEmail($email)
            ->setOrganization($organization)
            ->setRoles(['ROLE_ORG_ADMIN'])
            ->setAccountStatus(UserAccountStatus::ACTIVE)
            ->setEmailVerifiedAt(new \DateTimeImmutable())
            ->setEmailVerificationTokenHash(null)
            ->setEmailVerificationExpiresAt(null);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $this->entityManager->persist($organization);
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $issued = $this->refreshTokenService->issue($user);
            $refreshToken = $issued['refreshToken'];

            $accessToken = $this->jwtTokenManager->createFromPayload($user, [
                'tenantId' => (string) $organization->getId(),
                'roles' => $user->getRoles(),
                'sid' => $refreshToken->getId(),
                'iat' => time(),
            ]);

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        // Stripe customer created after DB commit to avoid orphaned customers on DB failure
        $stripeCustomerId = $this->stripeService->createCustomer($organization, $email, $selectedPlan);
        $organization->setStripeCustomerId($stripeCustomerId);
        $this->entityManager->flush();

        return new JsonResponse([
            'token' => $accessToken,
            'refreshToken' => $issued['plainToken'],
            'expiresIn' => 900,
            'organization' => [
                'id' => (string) $organization->getId(),
                'name' => $organization->getName(),
                'plan' => $organization->getPlan()->value,
                'licenseStatus' => $organization->getLicenseStatus()->value,
            ],
            'selectedPlan' => $selectedPlan->value,
            'nextPath' => '/kyb',
        ], Response::HTTP_CREATED);
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
    private function resolveOrganizationName(array $payload): string
    {
        $organizationName = trim((string) ($payload['organizationName'] ?? ''));

        if ($organizationName === '') {
            throw new InvalidArgumentException('Organization name is required.');
        }

        return $organizationName;
    }

    /** @param array<string, mixed> $payload */
    private function resolveCountry(array $payload): string
    {
        $country = strtoupper(trim((string) ($payload['country'] ?? 'FR')));

        if (!preg_match('/^[A-Z]{2}$/', $country)) {
            throw new InvalidArgumentException('Country must be a valid ISO 3166-1 alpha-2 code.');
        }

        return $country;
    }

    /** @param array<string, mixed> $payload */
    private function resolveSelectedPlan(array $payload): SubscriptionPlan
    {
        $rawPlan = strtolower(trim((string) ($payload['plan'] ?? SubscriptionPlan::STARTER->value)));

        return match ($rawPlan) {
            SubscriptionPlan::STARTER->value => SubscriptionPlan::STARTER,
            SubscriptionPlan::PRO->value => SubscriptionPlan::PRO,
            SubscriptionPlan::BUSINESS->value => SubscriptionPlan::BUSINESS,
            default => throw new InvalidArgumentException('Selected plan is not available for self-serve onboarding.'),
        };
    }
}
