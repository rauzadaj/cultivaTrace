<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
#[ApiResource]
class ActiviteCulturale
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int \$id = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string \$type = null;

    /**
     * @ORM\Column(type="datetime")
     */
    private ?\DateTimeInterface \$date = null;

    /**
     * @ORM\ManyToOne(targetEntity=Parcelle::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Parcelle \$parcelle = null;

    public function getId(): ?int
    {
        return \$this->id;
    }

    public function getType(): ?string
    {
        return \$this->type;
    }

    public function setType(string \$type): self
    {
        \$this->type = \$type;

        return \$this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return \$this->date;
    }

    public function setDate(\DateTimeInterface \$date): self
    {
        \$this->date = \$date;

        return \$this;
    }

    public function getParcelle(): ?Parcelle
    {
        return \$this->parcelle;
    }

    public function setParcelle(?Parcelle \$parcelle): self
    {
        \$this->parcelle = \$parcelle;

        return \$this;
    }
}
