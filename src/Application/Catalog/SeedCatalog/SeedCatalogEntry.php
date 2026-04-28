<?php

declare(strict_types=1);

namespace App\Application\Catalog\SeedCatalog;

final readonly class SeedCatalogEntry
{
    /**
     * @param array<int, string> $markets
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $vendor,
        public string $genetics,
        public string $description,
        public string $imageUrl,
        public string $sourceUrl,
        public string $sourceModifiedAt,
        public array $markets,
        public array $metadata,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'vendor' => $this->vendor,
            'genetics' => $this->genetics,
            'description' => $this->description,
            'imageUrl' => $this->imageUrl,
            'sourceUrl' => $this->sourceUrl,
            'sourceModifiedAt' => $this->sourceModifiedAt,
            'markets' => $this->markets,
            'metadata' => $this->metadata,
        ];
    }
}
