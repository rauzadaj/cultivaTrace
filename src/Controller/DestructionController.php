<?php

namespace App\Controller;

use App\Entity\Plant;
use App\Entity\DestructionIntent;
use App\Service\DestructionWorkflowService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class DestructionController extends AbstractController
{
    public function __construct(
        private readonly DestructionWorkflowService $destructionWorkflow,
    ) {}

    /**
     * Étape 1 — Déclarer l'intention de destruction.
     * POST /api/plants/{id}/destroy
     */
    #[Route('/api/plants/{id}/destroy', methods: ['POST'])]
    public function intent(Plant $plant, Request $request, #[CurrentUser] $user): JsonResponse
    {
        try {
            $intent = $this->destructionWorkflow->declareIntent(
                $plant,
                $user,
                (string) ((json_decode($request->getContent(), true) ?? [])['reason'] ?? ''),
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

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
        try {
            $this->destructionWorkflow->confirmIntent(
                $intent,
                $user,
                json_decode($request->getContent(), true) ?? [],
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'error' => $exception->getMessage(),
                'legalDateMin' => $intent->getLegalDateMin()->format('Y-m-d'),
                'daysRemaining' => $intent->getDaysRemaining(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(['status' => 'confirmed'], Response::HTTP_OK);
    }
}
