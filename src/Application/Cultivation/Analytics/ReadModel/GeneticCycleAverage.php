<?php

namespace App\Application\Cultivation\Analytics\ReadModel;

final readonly class GeneticCycleAverage
{
    public function __construct(
        public string $geneticId,
        public string $geneticCode,
        public string $geneticName,
        public int $completedCycles,
        public float $averageCycleDays,
    ) {
    }

    /** @return array<string, int|float|string> */
    public function toArray(): array
    {
        return [
            'geneticId' => $this->geneticId,
            'geneticCode' => $this->geneticCode,
            'geneticName' => $this->geneticName,
            'completedCycles' => $this->completedCycles,
            'averageCycleDays' => round($this->averageCycleDays, 2),
        ];
    }
}
