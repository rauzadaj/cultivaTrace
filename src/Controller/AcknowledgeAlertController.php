<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Alert;
use App\Entity\User;
use App\Security\Voter\TenantAwareVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/alerts/{id}/acknowledge', methods: ['POST'])]
final class AcknowledgeAlertController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(Alert $alert, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authenticated user required.');
        }

        if (!$this->isGranted('ROLE_ORG_USER')) {
            throw new AccessDeniedHttpException('Insufficient role for alert acknowledge.');
        }

        if (!$this->isGranted(TenantAwareVoter::ACCESS, $alert)) {
            throw new AccessDeniedHttpException('Cross-tenant alert acknowledge is forbidden.');
        }

        if (!$alert->isAcknowledged()) {
            $alert->setAcknowledgedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }

        return $this->json([
            'id' => (string) $alert->getId(),
            'type' => $alert->getType(),
            'title' => $alert->getTitle(),
            'message' => $alert->getMessage(),
            'severity' => $alert->getSeverity(),
            'context' => $alert->getContext(),
            'metadata' => $alert->getMetadata(),
            'createdAt' => $alert->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'acknowledgedAt' => $alert->getAcknowledgedAt()?->format(\DateTimeInterface::ATOM),
        ]);
    }
}
