<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * TenantFilter — global Doctrine filter that automatically injects
 * WHERE tenant_id = :tenantId on all queries.
 *
 * Enabled in TenantListener after JWT authentication.
 * DO NOT disable this filter except in maintenance CLI commands.
 *
 * Required configuration in config/packages/doctrine.yaml:
 *
 *   doctrine:
 *     orm:
 *       filters:
 *         tenant_filter:
 *           class: App\Doctrine\TenantFilter
 *           enabled: false  # enabled dynamically by TenantListener
 */
class TenantFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        // Apply only to entities that have a tenantId field
        if (!$targetEntity->hasField('tenantId')) {
            return '';
        }

        $column = $targetEntity->getColumnName('tenantId');
        $quoted = $this->getParameter('tenantId');

        // On SQLite, Doctrine stores UUIDs as 16-byte binary strings (TEXT affinity
        // via PDO). A direct = comparison against a string UUID fails because the
        // stored bytes do not match the RFC-4122 text representation.
        // Using hex(column) = 'UPPERCASE_HEX' correctly compares the binary bytes.
        if ($this->getConnection()->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform) {
            $tenantId = trim($quoted, "'");
            try {
                $hex = strtoupper(bin2hex(\Symfony\Component\Uid\Uuid::fromString($tenantId)->toBinary()));
                return sprintf("hex(%s.%s) = '%s'", $targetTableAlias, $column, $hex);
            } catch (\Throwable) {
                return '';
            }
        }

        return sprintf('%s.%s = %s', $targetTableAlias, $column, $quoted);
    }
}
