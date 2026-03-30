<?php

namespace App\Tests\Infrastructure\Http\Controller;

use App\Entity\User;
use App\Infrastructure\Http\Controller\RegisterUserController;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class RegisterUserControllerTest extends TestCase
{
    public function testItCreatesANewAccount(): void
    {
        $userRepository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $userRepository->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'operator@cultivatrace.local'])
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush');
        $entityManager->method('getRepository')->with(User::class)->willReturn($userRepository);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects(self::once())
            ->method('hashPassword')
            ->with(self::isInstanceOf(User::class), 'strong-pass')
            ->willReturn('hashed-password');

        $controller = new RegisterUserController(
            $entityManager,
            $passwordHasher,
            $this->createAcceptedRateLimiterFactory(),
        );
        $response = $controller->__invoke(new Request(content: json_encode([
            'email' => 'operator@cultivatrace.local',
            'password' => 'strong-pass',
        ], JSON_THROW_ON_ERROR)));

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertStringContainsString('operator@cultivatrace.local', $response->getContent() ?: '');
    }

    public function testItRejectsDuplicateEmails(): void
    {
        $existingUser = (new User())->setEmail('operator@cultivatrace.local');

        $userRepository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $userRepository->expects(self::once())
            ->method('findOneBy')
            ->willReturn($existingUser);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');
        $entityManager->method('getRepository')->with(User::class)->willReturn($userRepository);

        $controller = new RegisterUserController(
            $entityManager,
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createAcceptedRateLimiterFactory(),
        );

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('An account already exists for this email address.');

        $controller->__invoke(new Request(content: json_encode([
            'email' => 'operator@cultivatrace.local',
            'password' => 'strong-pass',
        ], JSON_THROW_ON_ERROR)));
    }

    public function testItRejectsShortPasswords(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getRepository');

        $controller = new RegisterUserController(
            $entityManager,
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createAcceptedRateLimiterFactory(),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must contain at least 8 characters.');

        $controller->__invoke(new Request(content: json_encode([
            'email' => 'operator@cultivatrace.local',
            'password' => 'short',
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
        );

        $request = new Request(
            server: ['REMOTE_ADDR' => '203.0.113.10'],
            content: json_encode([
                'email' => 'operator@cultivatrace.local',
                'password' => 'strong-pass',
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
        $factory->create('203.0.113.10')->consume();

        return $factory;
    }

    private function createRateLimiterFactory(): RateLimiterFactory
    {
        return new RateLimiterFactory([
            'id' => 'api_register',
            'policy' => 'fixed_window',
            'limit' => 1,
            'interval' => '15 minutes',
        ], new InMemoryStorage());
    }
}
