<?php

declare(strict_types=1);

namespace App\Mailbox\Application;

/**
 * Frontière Mailbox → Prospection/Répertoire : à QUI envoyer pour cette piste.
 * Contact désigné d'abord, sinon premier contact avec email — RGPD compris.
 */
interface RecipientResolver
{
    public function resolve(string $tenantId, string $leadId): ?Recipient;
}
