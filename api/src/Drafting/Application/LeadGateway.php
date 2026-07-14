<?php

declare(strict_types=1);

namespace App\Drafting\Application;

/**
 * Frontière de contexte Drafting → Prospection/Répertoire.
 * Tenant EXPLICITE (fail-closed par construction) : le worker n'a pas de
 * contexte de requête, le tenant vient de la commande ou de l'event.
 */
interface LeadGateway
{
    public function context(string $tenantId, string $leadId): ?LeadContext;
}
