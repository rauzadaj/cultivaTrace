<?php

namespace App\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * TenantFilter — filtre Doctrine global qui injecte automatiquement
 * WHERE tenant_id = :tenantId sur toutes les requêtes.
 *
 * Activé dans TenantListener après authentification JWT.
 * NE PAS désactiver ce filtre sauf dans les commandes CLI de maintenance.
 *
 * Configuration requise dans config/packages/doctrine.yaml :
 *
 *   doctrine:
 *     orm:
 *       filters:
 *         tenant_filter:
 *           class: App\Doctrine\TenantFilter
 *           enabled: false  # activé dynamiquement par TenantListener
 */
class TenantFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        // Appliquer uniquement aux entités qui ont un champ tenantId
        if (!$targetEntity->hasField('tenantId')) {
            return '';
        }

        $column = $targetEntity->getColumnName('tenantId');

        return sprintf(
            '%s.%s = %s',
            $targetTableAlias,
            $column,
            $this->getParameter('tenantId') // UUID sous forme de string
        );
    }
}
