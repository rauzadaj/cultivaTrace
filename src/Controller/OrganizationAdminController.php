<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\OrganizationInvitation;
use App\Entity\User;
use App\Repository\OrganizationInvitationRepository;
use App\Service\Auth\OrganizationInvitationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class OrganizationAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrganizationInvitationService $invitationService,
        private readonly OrganizationInvitationRepository $invitationRepository,
    ) {
    }

    #[Route('/api/organization/settings', methods: ['GET'])]
    public function settings(#[CurrentUser] ?User $user): JsonResponse
    {
        $organization = $this->assertOrganizationAdmin($user)->getOrganization();

        return $this->json([
            'id' => (string) $organization->getId(),
            'name' => $organization->getName(),
            'country' => $organization->getCountry(),
            'contactEmail' => $organization->getContactEmail(),
            'config' => $organization->getConfig(),
            'plan' => $organization->getPlan()->value,
            'licenseStatus' => $organization->getLicenseStatus()->value,
            'licenseExpiresAt' => $organization->getLicenseExpiresAt()?->format(DATE_ATOM),
            'stripeCustomerId' => $organization->getStripeCustomerId() ? '***' : null,
        ]);
    }

    #[Route('/api/organization/settings', methods: ['PATCH'])]
    public function updateSettings(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $organization = $this->assertOrganizationAdmin($user)->getOrganization();
        $data = json_decode($request->getContent(), true) ?? [];

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                return $this->json(['error' => 'Organization name is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $organization->setName($name);
        }

        if (array_key_exists('contactEmail', $data)) {
            $contactEmail = trim((string) $data['contactEmail']);

            if ($contactEmail !== '' && false === filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
                return $this->json(['error' => 'Contact email must be valid.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $organization->setContactEmail($contactEmail === '' ? null : $contactEmail);
        }

        if (array_key_exists('config', $data)) {
            if (!is_array($data['config'])) {
                return $this->json(['error' => 'Config must be a JSON object.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $organization->setConfig($data['config']);
        }

        $this->entityManager->flush();

        return $this->settings($user);
    }

    #[Route('/api/organization/members', methods: ['GET'])]
    public function members(#[CurrentUser] ?User $user): JsonResponse
    {
        $organization = $this->assertOrganizationAdmin($user)->getOrganization();

        $members = array_map(
            static fn (User $member): array => [
                'id' => (string) $member->getId(),
                'email' => $member->getEmail(),
                'roles' => $member->getRoles(),
                'accountStatus' => $member->getAccountStatus()->value,
                'emailVerifiedAt' => $member->getEmailVerifiedAt()?->format(DATE_ATOM),
            ],
            $organization->getUsers()->toArray(),
        );

        return $this->json([
            'members' => $members,
        ]);
    }

    #[Route('/api/organization/invitations', methods: ['GET'])]
    public function invitations(#[CurrentUser] ?User $user): JsonResponse
    {
        $organization = $this->assertOrganizationAdmin($user)->getOrganization();

        $invitations = array_map(
            static fn (OrganizationInvitation $invitation): array => [
                'id' => (string) $invitation->getId(),
                'email' => $invitation->getEmail(),
                'roles' => $invitation->getRoles(),
                'createdAt' => $invitation->getCreatedAt()->format(DATE_ATOM),
                'expiresAt' => $invitation->getExpiresAt()->format(DATE_ATOM),
            ],
            $this->invitationRepository->findPendingForOrganization($organization),
        );

        return $this->json([
            'invitations' => $invitations,
        ]);
    }

    #[Route('/api/organization/invitations', methods: ['POST'])]
    public function invite(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $invitedBy = $this->assertOrganizationAdmin($user);
        $data = json_decode($request->getContent(), true) ?? [];
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $role = trim((string) ($data['role'] ?? 'ROLE_ORG_USER'));

        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'A valid email address is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!in_array($role, ['ROLE_ORG_ADMIN', 'ROLE_ORG_USER'], true)) {
            return $this->json(['error' => 'Invitation role is invalid.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var User|null $existingUser */
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser instanceof User) {
            return $this->json(['error' => 'A user already exists for this email address.'], Response::HTTP_CONFLICT);
        }

        $invitation = $this->invitationService->issueInvitation(
            $invitedBy->getOrganization(),
            $invitedBy,
            $email,
            [$role],
        );

        return $this->json([
            'id' => (string) $invitation->getId(),
            'email' => $invitation->getEmail(),
            'roles' => $invitation->getRoles(),
            'expiresAt' => $invitation->getExpiresAt()->format(DATE_ATOM),
        ], Response::HTTP_CREATED);
    }

    private function assertOrganizationAdmin(?User $user): User
    {
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authenticated user required.');
        }

        if (!array_intersect($user->getRoles(), ['ROLE_ORG_ADMIN', 'ROLE_SUPER_ADMIN'])) {
            throw $this->createAccessDeniedException('Organization admin role required.');
        }

        if (!$user->hasOrganization()) {
            throw $this->createAccessDeniedException('Authenticated user must belong to an organization.');
        }

        return $user;
    }
}
