<?php

declare(strict_types=1);

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
     * Step 1 of KYB: submit the license number + document.
     * Triggers automatic verification in the background.
     *
     * Body (multipart/form-data) :
     *   licenseNumber : string (required)
     *   licenseType   : metrc_usa | health_canada | bfarm_de | ansm_fr | ctls_dev (dev/test only)
     *   file          : PDF or image file (optional in dev)
     */
    #[Route('/api/kyb/upload', methods: ['POST'])]
    public function upload(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $org = $this->assertOrganizationWriter($user);

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
            return $this->json(['error' => 'licenseNumber and licenseType are required'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validTypes = ['metrc_usa', 'health_canada', 'bfarm_de', 'ansm_fr'];
        if ($this->kybService->isDevSimulationEnabled()) {
            $validTypes[] = 'ctls_dev';
        }

        if (!in_array($licenseType, $validTypes, true)) {
            return $this->json([
                'error'       => 'Invalid licenseType',
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
            return $this->json(['error' => 'The provided file is invalid.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'id'                 => (string) $license->getId(),
            'licenseNumber'      => $license->getLicenseNumber(),
            'status'             => $license->getStatus(),
            'verificationMethod' => $license->getVerificationMethod(),
            'message'            => match ($license->getStatus()) {
                'active'  => 'License verified and activated. Full access unlocked.',
                'pending' => 'License submitted. Manual verification in progress (24-48h).',
                'rejected'=> 'License not recognized. Please check your number and try again.',
                default   => 'Unknown status.',
            },
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/kyb/status
     *
     * Returns the KYB status of the current organization.
     */
    #[Route('/api/kyb/status', methods: ['GET'])]
    public function status(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->hasOrganization()) {
            return $this->json(['error' => 'No organization found'], Response::HTTP_NOT_FOUND);
        }

        $org = $user->getOrganization();

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
     * CultivaTrace admin backoffice — manually approve or reject a license.
     * Access restricted to ROLE_SUPER_ADMIN only.
     */
    #[Route('/api/kyb/admin/validate/{id}', methods: ['POST'])]
    public function adminValidate(LicenseDocument $license, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $data   = json_decode($request->getContent(), true) ?? [];
        $action = $data['action'] ?? null; // approve | reject
        $reason = $data['reason'] ?? null;

        if (!in_array($action, ['approve', 'reject'], true)) {
            return $this->json(['error' => 'action must be approve or reject'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = [
            'verified' => $action === 'approve',
            'method'   => 'manual',
            'reviewed' => true,
            'reason'   => $reason,
        ];

        if ($action === 'approve' && isset($data['expiresAt'])) {
            try {
                $expiresAt = new \DateTimeImmutable($data['expiresAt']);
            } catch (\Throwable) {
                return $this->json(['error' => 'Invalid expiresAt date format. Use YYYY-MM-DD.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Normalize both bounds to midnight for date-only comparison; $expiresAt is parsed
            // from YYYY-MM-DD so it is already at 00:00:00, but DateTimeImmutable('+1 day') is
            // "now + 24h" which rejects a valid tomorrow date for most of the day.
            $minDate = new \DateTimeImmutable('tomorrow midnight');
            $maxDate = (new \DateTimeImmutable('today midnight'))->modify('+5 years');

            if ($expiresAt < $minDate || $expiresAt > $maxDate) {
                return $this->json(
                    ['error' => 'expiresAt must be between tomorrow and 5 years from now.'],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $result['expiresAt'] = $expiresAt->format('Y-m-d');
        }

        $this->kybService->applyVerificationResult($license, $result);

        return $this->json([
            'status'  => $license->getStatus(),
            'message' => $action === 'approve' ? 'License approved' : 'License rejected',
        ]);
    }

    private function assertOrganizationWriter(?User $user): \App\Entity\Organization
    {
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authenticated user required.');
        }

        if (!array_intersect($user->getRoles(), ['ROLE_ORG_USER', 'ROLE_ORG_ADMIN', 'ROLE_SUPER_ADMIN'])) {
            throw $this->createAccessDeniedException('Only organization members can submit KYB documents.');
        }

        if (!$user->hasOrganization()) {
            throw $this->createAccessDeniedException('Authenticated user must belong to an organization.');
        }

        return $user->getOrganization();
    }
}
