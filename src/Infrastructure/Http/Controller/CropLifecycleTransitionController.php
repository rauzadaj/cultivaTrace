<?php

namespace App\Infrastructure\Http\Controller;

use App\Application\Cultivation\Workflow\CropLifecycleManager;
use App\Domain\Cultivation\Model\Crop;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final readonly class CropLifecycleTransitionController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CropLifecycleManager $cropLifecycleManager,
    ) {
    }

    #[Route('/api/crops/{id}/transitions/{transition}', name: 'api_crop_lifecycle_transition', methods: ['POST'])]
    public function __invoke(string $id, string $transition, Request $request): JsonResponse
    {
        $crop = $this->entityManager->find(Crop::class, $id);

        if (!$crop instanceof Crop) {
            throw new NotFoundHttpException('Crop not found.');
        }

        $payload = [] === $request->request->all()
            ? ($request->getContent() !== '' ? $request->toArray() : [])
            : $request->request->all();

        match ($transition) {
            'start_vegetative' => $this->cropLifecycleManager->moveToVegetative($crop),
            'start_flowering' => $this->cropLifecycleManager->moveToFlowering($crop),
            'harvest' => $this->cropLifecycleManager->harvest(
                $crop,
                $this->resolveFinalYieldGrams($payload),
                isset($payload['harvestedAt']) ? new \DateTimeImmutable((string) $payload['harvestedAt']) : null,
            ),
            default => throw new NotFoundHttpException('Unknown crop transition.'),
        };

        $this->entityManager->persist($crop);
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $crop->getId(),
            'currentStage' => $crop->getCurrentStage()->value,
            'harvestedAt' => $crop->getHarvestedAt()?->format(\DateTimeInterface::ATOM),
            'finalYieldGrams' => $crop->getFinalYieldGrams(),
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function resolveFinalYieldGrams(array $payload): int
    {
        if (!array_key_exists('finalYieldGrams', $payload)) {
            throw new InvalidArgumentException('Harvest transition requires "finalYieldGrams".');
        }

        $finalYield = filter_var(
            $payload['finalYieldGrams'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0]],
        );

        if (false === $finalYield) {
            throw new InvalidArgumentException('Harvest transition requires a non-negative integer "finalYieldGrams".');
        }

        return $finalYield;
    }
}
