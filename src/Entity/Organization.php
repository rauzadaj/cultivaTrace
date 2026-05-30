<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Enum\LicenseStatus;
use App\Enum\SubscriptionPlan;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Organization is the root tenant.
 * Every user belongs to an Organization.
 * The tenantId of all business entities = Organization::$id
 *
 * DO NOT expose this entity via a public API directly.
 * Access is via /me or /organizations/{id} (ADMIN only).
 */
#[ORM\Entity(repositoryClass: \App\Repository\OrganizationRepository::class)]
#[ORM\Table(name: 'organization')]
#[ApiResource(operations: [])] // no direct public CRUD operation
class Organization
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name;

    #[ORM\Column(length: 2)]
    private string $country; // FR, DE, CA, US, BE, ES

    #[ORM\Column(length: 50, enumType: SubscriptionPlan::class)]
    private SubscriptionPlan $plan = SubscriptionPlan::STARTER;

    #[ORM\Column(length: 50, enumType: LicenseStatus::class)]
    private LicenseStatus $licenseStatus = LicenseStatus::PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $licenseExpiresAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactEmail = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true, 'default' => '{}'])]
    private array $config = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, User> */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'organization')]
    private Collection $users;

    /** @var Collection<int, Farm> */
    #[ORM\OneToMany(targetEntity: Farm::class, mappedBy: 'organization')]
    private Collection $farms;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->users = new ArrayCollection();
        $this->farms = new ArrayCollection();
    }

    public function getId(): Uuid { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getCountry(): string { return $this->country; }
    public function setCountry(string $country): self { $this->country = $country; return $this; }
    public function getPlan(): SubscriptionPlan { return $this->plan; }
    public function setPlan(SubscriptionPlan $plan): self { $this->plan = $plan; return $this; }
    public function getLicenseStatus(): LicenseStatus { return $this->licenseStatus; }
    public function setLicenseStatus(LicenseStatus $status): self { $this->licenseStatus = $status; return $this; }
    public function getLicenseExpiresAt(): ?\DateTimeImmutable { return $this->licenseExpiresAt; }
    public function setLicenseExpiresAt(?\DateTimeImmutable $date): self { $this->licenseExpiresAt = $date; return $this; }
    public function getStripeCustomerId(): ?string { return $this->stripeCustomerId; }
    public function setStripeCustomerId(?string $id): self { $this->stripeCustomerId = $id; return $this; }
    public function getContactEmail(): ?string { return $this->contactEmail; }
    public function setContactEmail(?string $contactEmail): self { $this->contactEmail = $contactEmail; return $this; }
    /** @return array<string, mixed> */
    public function getConfig(): array { return $this->config; }
    /** @param array<string, mixed> $config */
    public function setConfig(array $config): self { $this->config = $config; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    /** @return Collection<int, User> */
    public function getUsers(): Collection { return $this->users; }
    /** @return Collection<int, Farm> */
    public function getFarms(): Collection { return $this->farms; }

    public function isSuspended(): bool
    {
        return $this->licenseStatus === LicenseStatus::SUSPENDED
            || $this->licenseStatus === LicenseStatus::EXPIRED;
    }
}
