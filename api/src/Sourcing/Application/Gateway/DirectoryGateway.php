<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Gateway;

/**
 * Frontière vers le Répertoire (par port — jamais d'accès direct à l'agrégat
 * Organisation, ADR-0020). L'implémentation (Infrastructure) délègue au bus.
 */
interface DirectoryGateway
{
    /** @param string[] $segments */
    public function createOrganization(
        string $organizationId,
        string $tenantId,
        string $name,
        string $type,
        ?string $website,
        array $segments,
    ): void;

    /** Valide la cible d'une fusion (l'organisation existe dans ce tenant). */
    public function organizationExists(string $organizationId, string $tenantId): bool;
}
