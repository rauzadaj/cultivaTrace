<?php

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

/**
 * Organization est le tenant racine.
 * Chaque utilisateur appartient à une Organization.
 * Le tenantId de toutes les entités métier = Organization::$id
 *
 * NE PAS exposer cette entité via API publique directement.
 * L'accès se fait via /me ou /organizations/{id} (ADMIN uniquement).
 */
#[ORM\Entity]
#[ORM\Table(name: 'organization')]
#[ApiResource(operations: [])] // pas d'opération CRUD publique directe
class Organization
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 2)]
    private string $country; // FR, DE, CA, US, BE, ES

    #[ORM\Column(length: 50, enumType: SubscriptionPlan::class)]
    private SubscriptionPlan $plan = SubscriptionPlan::STARTER;

    #[ORM\Column(length: 50, enumType: LicenseStatus::class)]
    private LicenseStatus $licenseStatus = LicenseStatus::PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $licenseExpiresAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(targetEntity: Farm::class, mappedBy: 'organization')]
    private Collection $farms;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
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
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getFarms(): Collection { return $this->farms; }

    public function isSuspended(): bool
    {
        return $this->licenseStatus === LicenseStatus::SUSPENDED
            || $this->licenseStatus === LicenseStatus::EXPIRED;
    }
}
