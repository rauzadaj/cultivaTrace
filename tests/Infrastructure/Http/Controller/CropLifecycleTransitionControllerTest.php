<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Http\Controller;

use App\Application\Cultivation\Workflow\CropLifecycleManager;
use App\Domain\Cultivation\Model\Crop;
use App\Domain\Cultivation\Model\Genetic;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\LicenseStatus;
use App\Infrastructure\Http\Controller\CropLifecycleTransitionController;
use App\Service\License\LicenseGuard;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Workflow\WorkflowInterface;

final class CropLifecycleTransitionControllerTest extends TestCase
{
    public function testItReturnsNotFoundWhenCropDoesNotExist(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('find')
            ->with(Crop::class, 'missing')
            ->willReturn(null);

        $security = $this->createMock(Security::class);

        $controller = new CropLifecycleTransitionController(
            $entityManager,
            new CropLifecycleManager($this->createMock(WorkflowInterface::class)),
            $security,
            new LicenseGuard(),
        );

        $this->expectException(NotFoundHttpException::class);

        $controller->__invoke('missing', 'start_vegetative', Request::create('/api/crops/missing/transitions/start_vegetative', 'POST'));
    }

    public function testItRejectsInvalidHarvestYieldPayload(): void
    {
        $crop = (new Crop())
            ->setBatchCode('LOT-2026-WF-INVALID')
            ->setDisplayName('Invalid Yield Crop')
            ->setGenetic(
                (new Genetic())
                    ->setCode('WF-INV')
                    ->setName('Workflow Invalid'),
            );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('find')
            ->with(Crop::class, $crop->getId())
            ->willReturn($crop);

        $organization = (new Organization())
            ->setName('Org Workflow')
            ->setCountry('FR')
            ->setLicenseStatus(LicenseStatus::ACTIVE);
        $crop->setTenantId($organization->getId());
        $user = (new User())
            ->setEmail('workflow@test.local')
            ->setOrganization($organization)
            ->setRoles(['ROLE_ORG_USER'])
            ->setPassword('hashed-password');

        $security = $this->createMock(Security::class);
        $security
            ->method('getUser')
            ->willReturn($user);
        $security
            ->method('isGranted')
            ->willReturnCallback(static fn (string $attribute): bool => match ($attribute) {
                'ROLE_ORG_USER' => true,
                'ROLE_SUPER_ADMIN' => false,
                default => false,
            });

        $controller = new CropLifecycleTransitionController(
            $entityManager,
            new CropLifecycleManager($this->createMock(WorkflowInterface::class)),
            $security,
            new LicenseGuard(),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('non-negative integer');

        $controller->__invoke(
            $crop->getId(),
            'harvest',
            Request::create(
                sprintf('/api/crops/%s/transitions/harvest', $crop->getId()),
                'POST',
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['finalYieldGrams' => 'abc'], JSON_THROW_ON_ERROR),
            ),
        );
    }

    public function testItRejectsCrossTenantWrites(): void
    {
        $crop = (new Crop())
            ->setBatchCode('LOT-2026-WF-XT')
            ->setDisplayName('Cross Tenant Crop')
            ->setGenetic(
                (new Genetic())
                    ->setCode('WF-XT')
                    ->setName('Workflow Cross Tenant'),
            )
            ->setTenantId(\Symfony\Component\Uid\Uuid::v4());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('find')
            ->with(Crop::class, $crop->getId())
            ->willReturn($crop);

        $organization = (new Organization())
            ->setName('Org Workflow')
            ->setCountry('FR');
        $user = (new User())
            ->setEmail('workflow-xt@test.local')
            ->setOrganization($organization)
            ->setRoles(['ROLE_ORG_USER'])
            ->setPassword('hashed-password');

        $security = $this->createMock(Security::class);
        $security
            ->method('getUser')
            ->willReturn($user);
        $security
            ->method('isGranted')
            ->willReturnCallback(static fn (string $attribute): bool => match ($attribute) {
                'ROLE_ORG_USER' => true,
                'ROLE_SUPER_ADMIN' => false,
                default => false,
            });

        $controller = new CropLifecycleTransitionController(
            $entityManager,
            new CropLifecycleManager($this->createMock(WorkflowInterface::class)),
            $security,
            new LicenseGuard(),
        );

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Cross-tenant');

        $controller->__invoke(
            $crop->getId(),
            'start_vegetative',
            Request::create(sprintf('/api/crops/%s/transitions/start_vegetative', $crop->getId()), 'POST'),
        );
    }
}
