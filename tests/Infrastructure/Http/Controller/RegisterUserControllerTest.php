<?php

namespace App\Tests\Infrastructure\Http\Controller;

use App\Entity\Organization;
use App\Entity\User;
use App\Infrastructure\Http\Controller\RegisterUserController;
use App\Service\Auth\EmailVerificationService;
use App\Service\Auth\PasswordPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RegisterUserControllerTest extends TestCase
{
    public function testItCreatesANewPendingAccountAndReturnsGenericResponse(): void
    {
        $organization = (new Organization())
            ->setName('CultivaTrace')
            ->setCountry('FR');

        $userRepository = $this->createRepositoryMock(null);
        $organizationRepository = $this->createRepositoryMock($organization);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(User::class));
        $entityManager->expects(self::once())->method('flush');
        $entityManager->method('getRepository')->willReturnMap([
            [User::class, $userRepository],
            [Organization::class, $organizationRepository],
        ]);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects(self::once())
            ->method('hashPassword')
            ->with(self::isInstanceOf(User::class), 'StrongPass!123')
            ->willReturn('hashed-password');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::isInstanceOf(RawMessage::class));

        $controller = new RegisterUserController(
            $entityManager,
            $passwordHasher,
            $this->createAcceptedRateLimiterFactory(),
            new PasswordPolicy(),
            new EmailVerificationService(new \App\Service\Auth\TokenHasher()),
            $mailer,
            $this->createUrlGenerator(),
        );

        $response = $controller->__invoke(new Request(content: json_encode([
            'email' => 'operator@cultivatrace.local',
            'password' => 'StrongPass!123',
            'organizationId' => 'org-123',
        ], JSON_THROW_ON_ERROR)));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('verification email', (string) $response->getContent());
    }

    public function testItReturnsTheSameGenericResponseForDuplicateEmails(): void
    {
        $organization = (new Organization())
            ->setName('CultivaTrace')
            ->setCountry('FR');
        $existingUser = (new User())->setEmail('operator@cultivatrace.local');

        $userRepository = $this->createRepositoryMock($existingUser);
        $organizationRepository = $this->createRepositoryMock($organization);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');
        $entityManager->method('getRepository')->willReturnMap([
            [User::class, $userRepository],
            [Organization::class, $organizationRepository],
        ]);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $controller = new RegisterUserController(
            $entityManager,
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createAcceptedRateLimiterFactory(),
            new PasswordPolicy(),
            new EmailVerificationService(new \App\Service\Auth\TokenHasher()),
            $mailer,
            $this->createUrlGenerator(),
        );

        $response = $controller->__invoke(new Request(content: json_encode([
            'email' => 'operator@cultivatrace.local',
            'password' => 'StrongPass!123',
            'organizationId' => 'org-123',
        ], JSON_THROW_ON_ERROR)));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('verification email', (string) $response->getContent());
    }

    public function testItRejectsWeakPasswords(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getRepository');

        $controller = new RegisterUserController(
            $entityManager,
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createAcceptedRateLimiterFactory(),
            new PasswordPolicy(),
            new EmailVerificationService(new \App\Service\Auth\TokenHasher()),
            $this->createMock(MailerInterface::class),
            $this->createUrlGenerator(),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must contain at least 12 characters.');

        $controller->__invoke(new Request(content: json_encode([
            'email' => 'operator@cultivatrace.local',
            'password' => 'short',
            'organizationId' => 'org-123',
        ], JSON_THROW_ON_ERROR)));
    }

    public function testItRejectsRequestsWhenRateLimitIsExceeded(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getRepository');
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $controller = new RegisterUserController(
            $entityManager,
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createRejectedRateLimiterFactory(),
            new PasswordPolicy(),
            new EmailVerificationService(new \App\Service\Auth\TokenHasher()),
            $this->createMock(MailerInterface::class),
            $this->createUrlGenerator(),
        );

        $request = new Request(
            server: ['REMOTE_ADDR' => '203.0.113.10'],
            content: json_encode([
                'email' => 'operator@cultivatrace.local',
                'password' => 'StrongPass!123',
                'organizationId' => 'org-123',
            ], JSON_THROW_ON_ERROR),
        );

        $response = $controller->__invoke($request);
        $payload = json_decode($response->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        self::assertSame('Too many requests, please try again later.', $payload['error']);
    }

    private function createAcceptedRateLimiterFactory(): RateLimiterFactory
    {
        return $this->createRateLimiterFactory();
    }

    private function createRejectedRateLimiterFactory(): RateLimiterFactory
    {
        $factory = $this->createRateLimiterFactory();
        $factory->create('203.0.113.10')->consume(5);

        return $factory;
    }

    private function createRateLimiterFactory(): RateLimiterFactory
    {
        return new RateLimiterFactory([
            'id' => 'api_register',
            'policy' => 'fixed_window',
            'limit' => 5,
            'interval' => '15 minutes',
        ], new InMemoryStorage());
    }

    private function createRepositoryMock(mixed $result): EntityRepository
    {
        $repository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy', 'find'])
            ->getMock();
        $repository->method('findOneBy')->willReturn($result instanceof User ? $result : null);
        $repository->method('find')->willReturn($result instanceof Organization ? $result : null);

        return $repository;
    }

    private function createUrlGenerator(): UrlGeneratorInterface
    {
        $generator = $this->createMock(UrlGeneratorInterface::class);
        $generator->method('generate')->willReturn('https://cultivatrace.local/api/auth/verify-email?token=token');

        return $generator;
    }
}
