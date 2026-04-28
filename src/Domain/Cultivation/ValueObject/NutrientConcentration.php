<?php

namespace App\Domain\Cultivation\ValueObject;

use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Embeddable]
final class NutrientConcentration
{
    #[ORM\Column(name: 'ppm', type: 'integer', nullable: true)]
    private ?int $ppm = null;

    public function __construct(?int $ppm = null)
    {
        if (null !== $ppm) {
            $this->setPpm($ppm);
        }
    }

    #[Groups(['journal:read', 'journal:write'])]
    public function getPpm(): ?int
    {
        return $this->ppm;
    }

    #[Groups(['journal:read'])]
    public function getUnit(): string
    {
        return 'ppm';
    }

    public function setPpm(?int $ppm): self
    {
        if (null === $ppm) {
            $this->ppm = null;

            return $this;
        }

        if ($ppm < 0 || $ppm > 5000) {
            throw new InvalidArgumentException('Nutrient concentration must be between 0 and 5000 ppm.');
        }

        $this->ppm = $ppm;

        return $this;
    }
}
