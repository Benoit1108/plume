<?php

declare(strict_types=1);

namespace App\Prospecting\Application;

/**
 * Frontière de contexte : ce que la Prospection a le droit de savoir du Répertoire.
 * Implémenté en Infrastructure (lecture SQL scoppée tenant) — jamais d'accès
 * direct à l'agrégat Organization (cf. CLAUDE.md).
 */
interface OrganizationGateway
{
    public function exists(string $organizationId): bool;

    /** Faux si l'organisation est marquée « ne pas contacter » (RGPD). */
    public function isContactAllowed(string $organizationId): bool;

    public function hasContact(string $organizationId, string $contactId): bool;
}
