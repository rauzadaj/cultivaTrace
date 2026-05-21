<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Organization;
use App\Entity\Plant;
use App\Entity\User;
use App\Enum\LicenseStatus;
use App\Enum\SubscriptionPlan;
use App\Repository\PlantEventRepository;
use App\Service\License\LicenseGuard;
use App\Service\PlanLimitExceededException;
use App\Service\PlanLimitsService;
use App\State\PlantStateProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class PlantStateProcessorTest extends TestCase
{
    public function testProcessWraps402WhenPlanLimitExceeded(): void
    {
        $organization = new Organization();
        $organization->setName('Org Test');
        $organization->setLicenseStatus(LicenseStatus::ACTIVE);
        $organization->setPlan(SubscriptionPlan::STARTER);

        $user = new User();
        $user->setEmail('grower@test.local');
        $user->setOrganization($organization);
        $user->setRoles(['ROLE_ORG_USER']);
        $user->setPassword('hashed');

        $plant = new Plant();

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        // Real LicenseGuard — organization is ACTIVE so it passes
        $licenseGuard = new LicenseGuard();

        $planLimits = $this->createMock(PlanLimitsService::class);
        $planLimits
            ->expects(self::once())
            ->method('checkPlantLimit')
            ->willThrowException(
                new PlanLimitExceededException('Limite atteinte', 'plants', 200, 200, SubscriptionPlan::PRO)
            );

        $persistProcessor = $this->createMock(ProcessorInterface::class);
        $persistProcessor->expects(self::never())->method('process');

        $eventRepository = $this->createMock(PlantEventRepository::class);
        $eventRepository->expects(self::never())->method('appendEvent');

        $processor = new PlantStateProcessor(
            $persistProcessor,
            $tokenStorage,
            $eventRepository,
            $licenseGuard,
            $planLimits,
            $this->createMock(EntityManagerInterface::class),
        );

        $operation = new Post();

        $this->expectException(HttpException::class);

        try {
            $processor->process($plant, $operation, [], []);
        } catch (HttpException $e) {
            self::assertSame(402, $e->getStatusCode());

            $payload = json_decode($e->getMessage(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('plants', $payload['limitType']);
            self::assertSame(200, $payload['current']);
            self::assertSame('pro', $payload['upgradeTo']);

            throw $e;
        }
    }

    public function testProcessThrowsAccessDeniedWhenLicenseIsNotActive(): void
    {
        $organization = new Organization();
        $organization->setName('Org Pending');
        $organization->setLicenseStatus(LicenseStatus::PENDING);

        $user = new User();
        $user->setEmail('pending@test.local');
        $user->setOrganization($organization);
        $user->setRoles(['ROLE_ORG_USER']);
        $user->setPassword('hashed');

        $plant = new Plant();
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $licenseGuard = new LicenseGuard();

        $planLimits = $this->createMock(PlanLimitsService::class);
        $planLimits->expects(self::never())->method('checkPlantLimit');

        $persistProcessor = $this->createMock(ProcessorInterface::class);
        $persistProcessor->expects(self::never())->method('process');

        $processor = new PlantStateProcessor(
            $persistProcessor,
            $tokenStorage,
            $this->createMock(PlantEventRepository::class),
            $licenseGuard,
            $planLimits,
            $this->createMock(EntityManagerInterface::class),
        );

        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);

        $processor->process($plant, new Post(), [], []);
    }
}
