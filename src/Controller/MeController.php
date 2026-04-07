<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class MeController extends AbstractController
{
    #[Route('/api/me', methods: ['GET'])]
    public function __invoke(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user || !$user->hasOrganization()) {
            return $this->json(['message' => 'Utilisateur non authentifie'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $organization = $user->getOrganization();

        return $this->json([
            'id' => (string) $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'mfaEnabled' => false,
            'organization' => [
                'id' => (string) $organization->getId(),
                'name' => $organization->getName(),
                'plan' => $organization->getPlan()->value,
                'licenseStatus' => $organization->getLicenseStatus()->value,
            ],
        ]);
    }
}
