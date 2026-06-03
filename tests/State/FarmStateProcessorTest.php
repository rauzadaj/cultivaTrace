<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Farm;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\LicenseStatus;
use App\Enum\SubscriptionPlan;
use App\Service\License\LicenseGuard;
use App\Service\PlanLimitExceededException;
use App\Service\PlanLimitsService;
use App\State\FarmStateProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class FarmStateProcessorTest extends TestCase
{
    public function testProcessWraps402WhenFarmLimitExceeded(): void
    {
        $organization = new Organization();
        $organization->setName('Org At Site Limit');
        $organization->setLicenseStatus(LicenseStatus::ACTIVE);
        $organization->setPlan(SubscriptionPlan::GROWTH); // max 1 site

        $user = new User();
        $user->setEmail('grower@test.local');
        $user->setOrganization($organization);
        $user->setRoles(['ROLE_ORG_ADMIN']);
        $user->setPassword('hashed');

        $farm = new Farm();

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $planLimits = $this->createMock(PlanLimitsService::class);
        $planLimits
            ->expects(self::once())
            ->method('checkFarmLimit')
            ->willThrowException(
                new PlanLimitExceededException('Site limit reached (1/1).', 'farms', 1, 1, SubscriptionPlan::PRO)
            );

        $persistProcessor = $this->createMock(ProcessorInterface::class);
        $persistProcessor->expects(self::never())->method('process');

        $processor = new FarmStateProcessor(
            $persistProcessor,
            $tokenStorage,
            new LicenseGuard(),
            $this->createMock(EntityManagerInterface::class),
            $planLimits,
        );

        $this->expectException(HttpException::class);

        try {
            $processor->process($farm, new Post(), [], []);
        } catch (HttpException $e) {
            self::assertSame(402, $e->getStatusCode());

            $payload = json_decode($e->getMessage(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('farms', $payload['limitType']);
            self::assertSame(1, $payload['current']);
            self::assertSame('pro', $payload['upgradeTo']);

            throw $e;
        }
    }

    public function testProcessDoesNotCheckFarmLimitOnPatch(): void
    {
        $organization = new Organization();
        $organization->setName('Org Patch');
        $organization->setLicenseStatus(LicenseStatus::ACTIVE);
        $organization->setPlan(SubscriptionPlan::GROWTH);

        $user = new User();
        $user->setEmail('grower@test.local');
        $user->setOrganization($organization);
        $user->setRoles(['ROLE_ORG_ADMIN']);
        $user->setPassword('hashed');

        $farm = new Farm();
        $farm->setTenantId($organization->getId());

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $planLimits = $this->createMock(PlanLimitsService::class);
        $planLimits->expects(self::never())->method('checkFarmLimit');

        $persistProcessor = $this->createMock(ProcessorInterface::class);
        $persistProcessor->expects(self::once())->method('process')->willReturn($farm);

        $processor = new FarmStateProcessor(
            $persistProcessor,
            $tokenStorage,
            new LicenseGuard(),
            $this->createMock(EntityManagerInterface::class),
            $planLimits,
        );

        $processor->process($farm, new Patch(), [], ['previous_data' => $farm]);
    }
}
