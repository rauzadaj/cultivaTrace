<?php

namespace App\Controller;

use App\Entity\LicenseDocument;
use App\Entity\User;
use App\Service\KybSubmissionService;
use App\Service\KybService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class KybController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly KybService $kybService,
        private readonly KybSubmissionService $kybSubmissionService,
    ) {}

    /**
     * POST /api/kyb/upload
     *
     * Étape 1 du KYB : soumettre le numéro de licence + document.
     * Lance la vérification automatique en arrière-plan.
     *
     * Body (multipart/form-data) :
     *   licenseNumber : string (obligatoire)
     *   licenseType   : metrc_usa | health_canada | bfarm_de | ansm_fr | ctls_dev (dev/test only)
     *   file          : fichier PDF ou image (optionnel en dev)
     */
    #[Route('/api/kyb/upload', methods: ['POST'])]
    public function upload(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $org = $this->assertOrganizationWriter($user);
        if (!$org) {
            return $this->json(['error' => 'Aucune organisation associée à ce compte'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $payload = [];
        if ($request->request->count() > 0) {
            $payload = $request->request->all();
        } else {
            try {
                $payload = $request->toArray();
            } catch (\JsonException) {
                $payload = [];
            }
        }

        $licenseNumber = isset($payload['licenseNumber']) ? trim((string) $payload['licenseNumber']) : null;
        $licenseType   = isset($payload['licenseType']) ? trim((string) $payload['licenseType']) : null;

        if (!$licenseNumber || !$licenseType) {
            return $this->json(['error' => 'licenseNumber et licenseType sont obligatoires'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validTypes = ['metrc_usa', 'health_canada', 'bfarm_de', 'ansm_fr'];
        $appEnv = (string) $this->getParameter('kernel.environment');
        if (in_array($appEnv, ['dev', 'test'], true)) {
            $validTypes[] = 'ctls_dev';
        }

        if (!in_array($licenseType, $validTypes, true)) {
            return $this->json([
                'error'       => 'licenseType invalide',
                'valid_types' => $validTypes,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $license = $this->kybSubmissionService->submit(
                $org,
                (string) $licenseNumber,
                (string) $licenseType,
                $request->files->get('file'),
            );
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => 'Le fichier fourni est invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'id'                 => (string) $license->getId(),
            'licenseNumber'      => $license->getLicenseNumber(),
            'status'             => $license->getStatus(),
            'verificationMethod' => $license->getVerificationMethod(),
            'message'            => match ($license->getStatus()) {
                'active'  => 'Licence vérifiée et activée. Accès complet débloqué.',
                'pending' => 'Licence soumise. Vérification manuelle en cours (24-48h).',
                'rejected'=> 'Licence non reconnue. Vérifiez votre numéro et réessayez.',
                default   => 'Statut inconnu.',
            },
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/kyb/status
     *
     * Retourne le statut KYB de l'organisation courante.
     */
    #[Route('/api/kyb/status', methods: ['GET'])]
    public function status(#[CurrentUser] $user): JsonResponse
    {
        $org = $user->getOrganization();
        if (!$org) {
            return $this->json(['error' => 'Aucune organisation'], Response::HTTP_NOT_FOUND);
        }

        $license = $this->em->getRepository(LicenseDocument::class)
            ->findOneBy(['organization' => $org], ['submittedAt' => 'DESC']);

        return $this->json([
            'organizationId'   => (string) $org->getId(),
            'licenseStatus'    => $org->getLicenseStatus()->value,
            'plan'             => $org->getPlan()->value,
            'licenseExpiresAt' => $org->getLicenseExpiresAt()?->format('Y-m-d'),
            'lastDocument'     => $license ? [
                'id'            => (string) $license->getId(),
                'licenseNumber' => $license->getLicenseNumber(),
                'licenseType'   => $license->getLicenseType(),
                'status'        => $license->getStatus(),
                'submittedAt'   => $license->getSubmittedAt()->format('Y-m-d H:i'),
                'verifiedAt'    => $license->getVerifiedAt()?->format('Y-m-d H:i'),
            ] : null,
        ]);
    }

    /**
     * POST /api/kyb/admin/validate/{id}
     *
     * Backoffice admin CannaSaaS — valider ou rejeter manuellement une licence.
     * Accès ROLE_SUPER_ADMIN uniquement.
     */
    #[Route('/api/kyb/admin/validate/{id}', methods: ['POST'])]
    public function adminValidate(LicenseDocument $license, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $data   = json_decode($request->getContent(), true) ?? [];
        $action = $data['action'] ?? null; // approve | reject
        $reason = $data['reason'] ?? null;

        if (!in_array($action, ['approve', 'reject'], true)) {
            return $this->json(['error' => 'action doit être approve ou reject'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = [
            'verified' => $action === 'approve',
            'method'   => 'manual',
            'reason'   => $reason,
        ];

        if ($action === 'approve' && isset($data['expiresAt'])) {
            $result['expiresAt'] = $data['expiresAt'];
        }

        $this->kybService->applyVerificationResult($license, $result);

        return $this->json([
            'status'  => $license->getStatus(),
            'message' => $action === 'approve' ? 'Licence approuvée' : 'Licence rejetée',
        ]);
    }

    private function assertOrganizationWriter(?User $user): ?\App\Entity\Organization
    {
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authenticated user required.');
        }

        if (!array_intersect($user->getRoles(), ['ROLE_ORG_USER', 'ROLE_ORG_ADMIN', 'ROLE_SUPER_ADMIN'])) {
            throw $this->createAccessDeniedException('Insufficient role for KYB writes.');
        }

        return $user?->getOrganization();
    }
}
