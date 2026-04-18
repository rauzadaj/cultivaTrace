<?php

declare(strict_types=1);

namespace App\Exception;

final class LicenseNotApprovedException extends \RuntimeException
{
    public function __construct(string $status)
    {
        parent::__construct(sprintf('Tenant license status "%s" does not allow write operations.', $status));
    }
}
