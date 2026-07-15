<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Gateway;

/**
 * Frontière vers la Prospection (par port). Crée une Piste à partir d'une
 * candidate acceptée/fusionnée — l'implémentation délègue à `CreateLead`.
 */
interface ProspectingGateway
{
    public function createLead(
        string $leadId,
        string $tenantId,
        string $organizationId,
        string $languagePair,
        string $source,
        string $priority,
        string $segment,
    ): void;
}
