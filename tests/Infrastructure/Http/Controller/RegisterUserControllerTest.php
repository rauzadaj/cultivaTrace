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

        $controller = new RegisterUserController($entityManager, $passwordHasher);
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
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must contain at least 8 characters.');

        $controller->__invoke(new Request(content: json_encode([
            'email' => 'operator@cultivatrace.local',
            'password' => 'short',
        ], JSON_THROW_ON_ERROR)));
    }
}
