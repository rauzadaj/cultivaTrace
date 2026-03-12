<?php

namespace App\Tests\Command;

use App\Command\SeedDemoDataCommand;
use App\Domain\Cultivation\Model\Crop;
use App\Domain\Cultivation\Model\Genetic;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        ));

        self::assertSame(Command::FAILURE, $commandTester->execute([]));
        self::assertStringContainsString('restricted to dev and test', $commandTester->getDisplay());
    }

    public function testItSeedsDemoDataInTestEnvironment(): void
    {
        $userRepository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $userRepository->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'demo@cultivatrace.local'])
            ->willReturn(null);

        $geneticRepository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $geneticRepository->expects(self::exactly(2))
            ->method('findOneBy')
            ->willReturnOnConsecutiveCalls(null, null);

        $cropRepository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $cropRepository->expects(self::exactly(3))
            ->method('findOneBy')
            ->willReturnOnConsecutiveCalls(null, null, null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')
            ->willReturnMap([
                [User::class, $userRepository],
                [Genetic::class, $geneticRepository],
                [Crop::class, $cropRepository],
            ]);
        $entityManager->expects(self::exactly(6))->method('persist');
        $entityManager->expects(self::once())->method('flush');

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
        ));

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
        self::assertStringContainsString('Demo environment ready', $commandTester->getDisplay());
    }

    public function testItResetsTheDemoUserPasswordOnReseed(): void
    {
        $existingUser = (new User())
            ->setEmail('demo@cultivatrace.local')
            ->setPassword('stale-password-hash');

        $userRepository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $userRepository->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'demo@cultivatrace.local'])
            ->willReturn($existingUser);

        $geneticRepository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $geneticRepository->expects(self::exactly(2))
            ->method('findOneBy')
            ->willReturnOnConsecutiveCalls(null, null);

        $cropRepository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $cropRepository->expects(self::exactly(3))
            ->method('findOneBy')
            ->willReturnOnConsecutiveCalls(null, null, null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')
            ->willReturnMap([
                [User::class, $userRepository],
                [Genetic::class, $geneticRepository],
                [Crop::class, $cropRepository],
            ]);
        $entityManager->expects(self::exactly(5))->method('persist');
        $entityManager->expects(self::once())->method('flush');

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
        ));

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
        self::assertSame('fresh-demo-password-hash', $existingUser->getPassword());
    }
}
