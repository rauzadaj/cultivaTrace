<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SeedDemoDataCommand;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SeedDemoDataCommandTest extends TestCase
{
    private function makeCommand(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        KernelInterface $kernel,
        Connection $connection,
    ): SeedDemoDataCommand {
        return new SeedDemoDataCommand($em, $hasher, $kernel, $connection);
    }

    private function makeNullRepo(): EntityRepository
    {
        $repo = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $repo->method('findOneBy')->willReturn(null);

        return $repo;
    }

    public function testItFailsOutsideDevAndTestEnvironments(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getRepository');
        $entityManager->expects(self::never())->method('flush');

        $kernel = $this->createConfiguredMock(KernelInterface::class, [
            'getEnvironment' => 'prod',
        ]);

        $commandTester = new CommandTester($this->makeCommand(
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
        $nullRepo = $this->makeNullRepo();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($nullRepo);
        $entityManager->expects(self::atLeastOnce())->method('persist');
        $entityManager->expects(self::atLeastOnce())->method('flush');

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('hashed-demo-password');

        $kernel = $this->createConfiguredMock(KernelInterface::class, [
            'getEnvironment' => 'test',
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->method('executeStatement')->willReturn(0);

        $commandTester = new CommandTester($this->makeCommand(
            $entityManager,
            $passwordHasher,
            $kernel,
            $connection,
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

        $userRepo = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $userRepo->method('findOneBy')
            ->with(['email' => 'demo@cultivatrace.local'])
            ->willReturn($existingUser);

        $nullRepo = $this->makeNullRepo();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')
            ->willReturnCallback(static function (string $class) use ($userRepo, $nullRepo) {
                return $class === User::class ? $userRepo : $nullRepo;
            });
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

        $connection = $this->createMock(Connection::class);
        $connection->method('executeStatement')->willReturn(0);

        $commandTester = new CommandTester($this->makeCommand(
            $entityManager,
            $passwordHasher,
            $kernel,
            $connection,
        ));

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
        self::assertSame('fresh-demo-password-hash', $existingUser->getPassword());
    }
}
