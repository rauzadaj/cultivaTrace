<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SeedDemoDataCommand;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SeedDemoDataCommandTest extends TestCase
{
    public function testItFailsOutsideDevAndTestEnvironments(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getRepository');
        $entityManager->expects(self::never())->method('flush');

        $kernel = $this->createConfiguredMock(KernelInterface::class, [
            'getEnvironment' => 'prod',
        ]);

        $commandTester = new CommandTester(new SeedDemoDataCommand(
            $entityManager,
            $this->createMock(UserPasswordHasherInterface::class),
            $kernel,
            $this->createMock(Connection::class),
        ));

        self::assertSame(Command::FAILURE, $commandTester->execute([]));
        self::assertStringContainsString('restricted to dev and test', $commandTester->getDisplay());
    }

    public function testItSeedsDemoDataInTestEnvironment(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')
            ->willReturn($this->emptyRepository());
        $entityManager->expects(self::atLeastOnce())->method('persist');
        $entityManager->expects(self::atLeastOnce())->method('flush');

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects(self::once())
            ->method('hashPassword')
            ->willReturn('hashed-demo-password');

        $kernel = $this->createConfiguredMock(KernelInterface::class, [
            'getEnvironment' => 'test',
        ]);

        $commandTester = new CommandTester(new SeedDemoDataCommand(
            $entityManager,
            $passwordHasher,
            $kernel,
            $this->createMock(Connection::class),
        ));

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
        self::assertStringContainsString('Demo environment ready', $commandTester->getDisplay());
    }

    public function testItResetsTheDemoUserPasswordOnReseed(): void
    {
        $organization = (new Organization())
            ->setName('Existing Demo Org')
            ->setCountry('FR');
        $existingUser = (new User())
            ->setEmail('demo@cultivatrace.local')
            ->setOrganization($organization)
            ->setPassword('stale-password-hash');

        $userRepository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $userRepository->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'demo@cultivatrace.local'])
            ->willReturn($existingUser);

        $emptyRepository = $this->emptyRepository();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')
            ->willReturnCallback(static fn (string $class) => $class === User::class ? $userRepository : $emptyRepository);
        $entityManager->expects(self::atLeastOnce())->method('persist');
        $entityManager->expects(self::atLeastOnce())->method('flush');

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects(self::once())
            ->method('hashPassword')
            ->with($existingUser, 'demo123')
            ->willReturn('fresh-demo-password-hash');

        $kernel = $this->createConfiguredMock(KernelInterface::class, [
            'getEnvironment' => 'test',
        ]);

        $commandTester = new CommandTester(new SeedDemoDataCommand(
            $entityManager,
            $passwordHasher,
            $kernel,
            $this->createMock(Connection::class),
        ));

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
        self::assertSame('fresh-demo-password-hash', $existingUser->getPassword());
    }

    /**
     * A repository stub whose findOneBy() always returns null so the command
     * creates every demo entity from scratch.
     */
    private function emptyRepository(): EntityRepository
    {
        $repository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $repository->method('findOneBy')->willReturn(null);

        return $repository;
    }
}
