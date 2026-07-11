<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Tenancy;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Isolation multi-tenant : ajoute `tenant_id = :tenant_id` sur toute entité
 * possédant un champ `tenantId`. Le paramètre est fixé à chaque requête depuis
 * le TenantContext (via un listener kernel.request — à câbler en M0-finalisation).
 */
final class TenantFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->hasField('tenantId')) {
            return '';
        }

        try {
            $tenantId = $this->getParameter('tenant_id');
        } catch (\InvalidArgumentException) {
            return '';
        }

        return sprintf('%s.tenant_id = %s', $targetTableAlias, $tenantId);
    }
}
