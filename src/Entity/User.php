<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\UserAccountStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\ManyToOne(targetEntity: Organization::class, inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Organization $organization = null;

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(enumType: UserAccountStatus::class, length: 32)]
    private UserAccountStatus $accountStatus = UserAccountStatus::PENDING_VERIFICATION;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $emailVerificationTokenHash = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailVerificationExpiresAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email ?? '';
    }

    public function getRoles(): array
    {
        return array_values(array_unique($this->roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = array_values(array_unique(array_filter(
            array_map(static fn (mixed $role): string => trim((string) $role), $roles),
            static fn (string $role): bool => $role !== '',
        )));

        return $this;
    }

    public function hasOrganization(): bool
    {
        return $this->organization instanceof Organization;
    }

    public function getOrganization(): Organization
    {
        if (!$this->organization instanceof Organization) {
            throw new \LogicException('User must belong to an organization.');
        }

        return $this->organization;
    }

    public function setOrganization(Organization $org): self
    {
        $this->organization = $org;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password ?? '';
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getAccountStatus(): UserAccountStatus
    {
        return $this->accountStatus;
    }

    public function setAccountStatus(UserAccountStatus $accountStatus): self
    {
        $this->accountStatus = $accountStatus;

        return $this;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(?\DateTimeImmutable $emailVerifiedAt): self
    {
        $this->emailVerifiedAt = $emailVerifiedAt;

        return $this;
    }

    public function getEmailVerificationTokenHash(): ?string
    {
        return $this->emailVerificationTokenHash;
    }

    public function setEmailVerificationTokenHash(?string $emailVerificationTokenHash): self
    {
        $this->emailVerificationTokenHash = $emailVerificationTokenHash;

        return $this;
    }

    public function getEmailVerificationExpiresAt(): ?\DateTimeImmutable
    {
        return $this->emailVerificationExpiresAt;
    }

    public function setEmailVerificationExpiresAt(?\DateTimeImmutable $emailVerificationExpiresAt): self
    {
        $this->emailVerificationExpiresAt = $emailVerificationExpiresAt;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }
}
