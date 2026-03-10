<?php

namespace App\Domain\Cultivation\ValueObject;

use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Embeddable]
final class PhLevel
{
    #[ORM\Column(name: 'value_centi', type: 'smallint', nullable: true)]
    private ?int $valueCenti = null;

    public function __construct(?float $value = null)
    {
        if (null !== $value) {
            $this->setValue($value);
        }
    }

    #[Groups(['journal:read', 'journal:write'])]
    public function getValue(): ?float
    {
        return null === $this->valueCenti ? null : $this->valueCenti / 100;
    }

    public function setValue(?float $value): self
    {
        if (null === $value) {
            $this->valueCenti = null;

            return $this;
        }

        if ($value < 0.0 || $value > 14.0) {
            throw new InvalidArgumentException('pH level must be between 0.00 and 14.00.');
        }

        $this->valueCenti = (int) round($value * 100);

        return $this;
    }
}
