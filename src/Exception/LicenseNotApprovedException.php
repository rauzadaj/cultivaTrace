<?php

declare(strict_types=1);

namespace App\Exception;

final class LicenseNotApprovedException extends \DomainException
{
    public function __construct(?string $status = null)
    {
        $message = 'Tenant license is not approved. Operation not permitted.';

        if ($status !== null) {
            $message = sprintf('Tenant license status "%s" does not allow write operations.', $status);
        }

        parent::__construct($message);
    }
}
