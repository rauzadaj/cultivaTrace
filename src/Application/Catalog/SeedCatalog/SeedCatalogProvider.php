<?php

declare(strict_types=1);

namespace App\Application\Catalog\SeedCatalog;

interface SeedCatalogProvider
{
    /**
     * @return list<SeedCatalogEntry>
     */
    public function fetchEntries(): array;
}
