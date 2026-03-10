<?php

namespace App\Tests\Infrastructure\Http\Controller;

use App\Application\Cultivation\Workflow\CropLifecycleManager;
use App\Domain\Cultivation\Model\Crop;
use App\Domain\Cultivation\Model\Genetic;
use App\Infrastructure\Http\Controller\CropLifecycleTransitionController;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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

        $controller = new CropLifecycleTransitionController(
            $entityManager,
            new CropLifecycleManager($this->createMock(WorkflowInterface::class)),
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

        $controller = new CropLifecycleTransitionController(
            $entityManager,
            new CropLifecycleManager($this->createMock(WorkflowInterface::class)),
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
}
