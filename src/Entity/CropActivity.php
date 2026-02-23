<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiResource]
class CropActivity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $type = null;

    #[ORM\Column]
    private ?DateTimeInterface $date = null;

    #[ORM\ManyToOne(targetEntity: Plot::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Plot $plot = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getDate(): ?DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getPlot(): ?Plot
    {
        return $this->plot;
    }

    public function setPlot(?Plot $plot): self
    {
        $this->plot = $plot;

        return $this;
    }
}
