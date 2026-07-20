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

    /**
     * Id de la piste active de l'organisation (invariant « une piste active par
     * organisation », M1.2), ou null si aucune. Permet à la fusion de rattacher
     * une annonce à la piste existante plutôt que d'en créer une seconde.
     */
    public function activeLeadId(string $tenantId, string $organizationId): ?string;

    /** Journalise une note sur une piste (ex. « annonce rattachée »). */
    public function annotateLead(string $leadId, string $text): void;
}
