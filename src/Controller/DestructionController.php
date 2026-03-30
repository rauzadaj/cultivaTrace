<?php

namespace App\Controller;

use App\Entity\Plant;
use App\Entity\DestructionIntent;
use App\Enum\PlantStatus;
use App\Repository\PlantEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class DestructionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PlantEventRepository $eventRepo,
    ) {}

    /**
     * Étape 1 — Déclarer l'intention de destruction.
     * POST /api/plants/{id}/destroy
     */
    #[Route('/api/plants/{id}/destroy', methods: ['POST'])]
    public function intent(Plant $plant, Request $request, #[CurrentUser] $user): JsonResponse
    {
        $this->assertDestructionAccess();

        if (!$plant->isActive()) {
            return $this->json([
                'error' => sprintf('Plant non destructible (statut : %s)', $plant->getStatus()->value),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data   = json_decode($request->getContent(), true) ?? [];
        $reason = $data['reason'] ?? null;

        if (!$reason) {
            return $this->json(['error' => 'La raison est obligatoire'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $intent = new DestructionIntent();
        $intent->setPlant($plant);
        $intent->setTenantId($plant->getTenantId());
        $intent->setReason($reason);
        $intent->setDeclaredBy($user);
        $this->em->persist($intent);

        $this->eventRepo->appendEvent(
            plant: $plant,
            eventType: 'destruction_intent',
            user: $user,
            payload: [
                'intentId'     => (string) $intent->getId(),
                'reason'       => $reason,
                'legalDateMin' => $intent->getLegalDateMin()->format('Y-m-d'),
            ],
        );

        $this->em->flush();

        return $this->json([
            'id'            => (string) $intent->getId(),
            'status'        => 'pending',
            'legalDateMin'  => $intent->getLegalDateMin()->format('Y-m-d'),
            'daysRemaining' => $intent->getDaysRemaining(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Étape 2 — Confirmer la destruction après le délai légal.
     * POST /api/destructions/{id}/confirm
     */
    #[Route('/api/destructions/{id}/confirm', methods: ['POST'])]
    public function confirm(DestructionIntent $intent, Request $request, #[CurrentUser] $user): JsonResponse
    {
        $this->assertDestructionAccess();

        if (!$intent->canBeConfirmed()) {
            return $this->json([
                'error'         => sprintf(
                    'Destruction impossible avant le %s (%d jour(s) restant(s))',
                    $intent->getLegalDateMin()->format('d/m/Y'),
                    $intent->getDaysRemaining()
                ),
                'legalDateMin'  => $intent->getLegalDateMin()->format('Y-m-d'),
                'daysRemaining' => $intent->getDaysRemaining(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data        = json_decode($request->getContent(), true) ?? [];
        $totalWeight = $data['totalWeightG'] ?? null;
        $ratio       = $data['nonCannabisRatio'] ?? null;
        $photoUrls   = $data['photoUrls'] ?? [];

        if (!$totalWeight || $ratio === null) {
            return $this->json(['error' => 'totalWeightG et nonCannabisRatio sont obligatoires'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ((float) $ratio < 0.50) {
            return $this->json([
                'error' => sprintf(
                    'Le ratio matières non-cannabis doit être >= 50%% (reçu : %.1f%%)',
                    (float) $ratio * 100
                ),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (empty($photoUrls)) {
            return $this->json(['error' => 'Au moins une photo est obligatoire'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->beginTransaction();
        try {
            $intent->setStatus('confirmed');
            $intent->setTotalWeightG((string) $totalWeight);
            $intent->setNonCannabisRatio((string) $ratio);
            $intent->setPhotoUrls($photoUrls);
            $intent->setConfirmedAt(new \DateTimeImmutable());
            $intent->setConfirmedBy($user);

            $plant = $intent->getPlant();
            $plant->setStatus(PlantStatus::DESTROYED);

            $this->eventRepo->appendEvent(
                plant: $plant,
                eventType: 'destruction_confirmed',
                user: $user,
                payload: [
                    'intentId'          => (string) $intent->getId(),
                    'totalWeightG'      => $totalWeight,
                    'nonCannabisRatio'  => $ratio,
                    'photoUrls'         => $photoUrls,
                ],
            );

            $this->em->flush();
            $this->em->commit();

        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        return $this->json(['status' => 'confirmed'], Response::HTTP_OK);
    }

    private function assertDestructionAccess(): void
    {
        if (
            !$this->isGranted('ROLE_OPERATOR')
            && !$this->isGranted('ROLE_MANAGER')
            && !$this->isGranted('ROLE_ADMIN')
        ) {
            throw $this->createAccessDeniedException();
        }
    }
}
