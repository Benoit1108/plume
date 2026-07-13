<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Tenancy;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Isolation multi-tenant : ajoute `tenant_id = :tenant_id` sur toute entité
 * possédant un champ `tenantId`. Le filtre est activé et paramétré par
 * TenantContextListener (claim JWT) à chaque requête authentifiée.
 *
 * Fail-closed : si le filtre est actif mais que le paramètre manque, on lève —
 * jamais de requête non scopée par accident. (Worker/CLI : le filtre est
 * désactivé par défaut ; tout futur handler asynchrone devra transporter le
 * tenant et réactiver le filtre explicitement.)
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
        } catch (\InvalidArgumentException $e) {
            throw new \LogicException(sprintf('Tenant filter enabled without tenant parameter while querying %s — refusing to run an unscoped query.', $targetEntity->getName()), 0, $e);
        }

        return sprintf('%s.tenant_id = %s', $targetTableAlias, $tenantId);
    }
}
