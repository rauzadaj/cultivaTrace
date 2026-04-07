<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Http\Controller;

use App\Entity\Organization;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Enum\SubscriptionPlan;
use App\Enum\UserAccountStatus;
use App\Infrastructure\Http\Controller\RegisterOrganizationController;
use App\Service\Auth\PasswordPolicy;
use App\Service\Auth\RefreshTokenService;
use App\Service\Auth\TokenHasher;
use App\Service\StripeService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use App\Repository\RefreshTokenRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class RegisterOrganizationControllerTest extends TestCase
{
    public function testItCreatesOrganizationAndAdminAndReturnsTokens(): void
    {
        $persisted = [];

        $userRepository = $this->createRepositoryMock(null);
        $organizationRepository = $this->createRepositoryMock(null);
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $connection->expects(self::never())->method('rollBack');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(3))
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });
        $entityManager->expects(self::exactly(2))->method('flush');
        $entityManager->expects(self::exactly(2))->method('getRepository')->willReturnMap([
            [User::class, $userRepository],
            [Organization::class, $organizationRepository],
        ]);
        $entityManager->method('getConnection')->willReturn($connection);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('hashed-password');

        $stripeService = $this->createMock(StripeService::class);
        $stripeService->expects(self::once())
            ->method('createCustomer')
            ->with(
                self::isInstanceOf(Organization::class),
                'admin@cultivatrace.local',
                SubscriptionPlan::PRO,
            )
            ->willReturn('cus_test_123');

        $refreshTokenRepository = new RefreshTokenRepository($this->createMock(ManagerRegistry::class));
        $refreshTokenService = new RefreshTokenService(
            $entityManager,
            $refreshTokenRepository,
            new TokenHasher(),
        );

        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->expects(self::once())
            ->method('createFromPayload')
            ->willReturn('jwt-access-token');

        $controller = new RegisterOrganizationController(
            $entityManager,
            $passwordHasher,
            $this->createAcceptedRateLimiterFactory(),
            new PasswordPolicy(),
            $stripeService,
            $jwtManager,
            $refreshTokenService,
        );

        $response = $controller->__invoke(new Request(content: json_encode([
            'organizationName' => 'CultivaTrace Org',
            'country' => 'CA',
            'email' => 'admin@cultivatrace.local',
            'password' => 'StrongPass!123',
            'plan' => 'pro',
        ], JSON_THROW_ON_ERROR)));

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertSame('jwt-access-token', $payload['token']);
        self::assertIsString($payload['refreshToken']);
        self::assertNotSame('', $payload['refreshToken']);
        self::assertSame('/kyb', $payload['nextPath']);
        self::assertSame('pro', $payload['selectedPlan']);

        $organization = array_values(array_filter(
            $persisted,
            static fn (object $entity): bool => $entity instanceof Organization,
        ))[0] ?? null;
        $user = array_values(array_filter(
            $persisted,
            static fn (object $entity): bool => $entity instanceof User,
        ))[0] ?? null;

        self::assertInstanceOf(Organization::class, $organization);
        self::assertInstanceOf(User::class, $user);
        self::assertSame('CultivaTrace Org', $organization->getName());
        self::assertSame('CA', $organization->getCountry());
        self::assertSame('cus_test_123', $organization->getStripeCustomerId());
        self::assertSame(['ROLE_ORG_ADMIN'], $user->getRoles());
        self::assertSame(UserAccountStatus::ACTIVE, $user->getAccountStatus());
        self::assertNotNull($user->getEmailVerifiedAt());
        self::assertContainsOnlyInstancesOf(
            RefreshToken::class,
            array_values(array_filter($persisted, static fn (object $entity): bool => $entity instanceof RefreshToken)),
        );
    }

    public function testItRejectsDuplicateEmails(): void
    {
        $organization = (new Organization())
            ->setName('CultivaTrace')
            ->setCountry('FR');
        $existingUser = (new User())
            ->setEmail('admin@cultivatrace.local')
            ->setOrganization($organization);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');
        $entityManager->method('getRepository')->willReturnMap([
            [User::class, $this->createRepositoryMock($existingUser)],
            [Organization::class, $this->createRepositoryMock(null)],
        ]);

        $controller = new RegisterOrganizationController(
            $entityManager,
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createAcceptedRateLimiterFactory(),
            new PasswordPolicy(),
            $this->createMock(StripeService::class),
            $this->createMock(JWTTokenManagerInterface::class),
            new RefreshTokenService(
                $entityManager,
                new RefreshTokenRepository($this->createMock(ManagerRegistry::class)),
                new TokenHasher(),
            ),
        );

        $response = $controller->__invoke(new Request(content: json_encode([
            'organizationName' => 'CultivaTrace Org',
            'email' => 'admin@cultivatrace.local',
            'password' => 'StrongPass!123',
            'plan' => 'starter',
        ], JSON_THROW_ON_ERROR)));

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    private function createAcceptedRateLimiterFactory(): RateLimiterFactory
    {
        return new RateLimiterFactory([
            'id' => 'api_register_organization',
            'policy' => 'fixed_window',
            'limit' => 5,
            'interval' => '15 minutes',
        ], new InMemoryStorage());
    }

    private function createRepositoryMock(mixed $result): EntityRepository
    {
        $repository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $repository->method('findOneBy')->willReturnCallback(
            static fn (array $criteria): mixed => $criteria === ['email' => 'admin@cultivatrace.local']
                ? ($result instanceof User ? $result : null)
                : ($result instanceof Organization ? $result : null),
        );

        return $repository;
    }
}
