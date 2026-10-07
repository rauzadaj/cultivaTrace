<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Organization;
use App\Entity\OrganizationInvitation;
use App\Entity\User;
use App\Enum\UserAccountStatus;
use App\Repository\OrganizationInvitationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class OrganizationInvitationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrganizationInvitationRepository $invitationRepository,
        private TokenHasher $tokenHasher,
        private UserPasswordHasherInterface $passwordHasher,
        private PasswordPolicy $passwordPolicy,
        private MailerInterface $mailer,
        #[Autowire('%env(FRONTEND_URL)%')]
        private string $frontendUrl,
    ) {
    }

    /**
     * @param list<string> $roles
     */
    public function issueInvitation(
        Organization $organization,
        User $invitedBy,
        string $email,
        array $roles,
    ): OrganizationInvitation {
        $plainToken = bin2hex(random_bytes(32));

        $invitation = (new OrganizationInvitation())
            ->setOrganization($organization)
            ->setInvitedBy($invitedBy)
            ->setEmail($email)
            ->setRoles($roles)
            ->setTokenHash($this->tokenHasher->hash($plainToken));

        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        $this->mailer->send(
            (new Email())
                ->from((string) $invitedBy->getEmail())
                ->to($email)
                ->subject('CultivaTrace invitation')
                ->text(sprintf(
                    "You have been invited to join %s on CultivaTrace.\n\nAccept the invitation: %s/invite/accept?token=%s",
                    $organization->getName(),
                    rtrim($this->frontendUrl, '/'),
                    $plainToken,
                )),
        );

        return $invitation;
    }

    public function acceptInvitation(string $plainToken, string $password): User
    {
        $this->passwordPolicy->assertValid($password);

        $invitation = $this->invitationRepository->findActiveByTokenHash(
            $this->tokenHasher->hash($plainToken),
            new \DateTimeImmutable(),
        );

        if (!$invitation instanceof OrganizationInvitation) {
            throw new \InvalidArgumentException('Invitation is invalid or expired.');
        }

        /** @var User|null $existingUser */
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $invitation->getEmail(),
        ]);

        if ($existingUser instanceof User) {
            throw new \InvalidArgumentException('An account already exists for this email address.');
        }

        $user = (new User())
            ->setEmail($invitation->getEmail())
            ->setOrganization($invitation->getOrganization() ?? throw new \LogicException('Invitation organization missing.'))
            ->setRoles($invitation->getRoles())
            ->setAccountStatus(UserAccountStatus::ACTIVE)
            ->setEmailVerifiedAt(new \DateTimeImmutable())
            ->setEmailVerificationTokenHash(null)
            ->setEmailVerificationExpiresAt(null);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $invitation->setAcceptedAt(new \DateTimeImmutable());

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
