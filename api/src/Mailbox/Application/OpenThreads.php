<?php

declare(strict_types=1);

namespace App\Mailbox\Application;

/** Fils encore « ouverts » : envoyés, piste pas encore en discussion. */
interface OpenThreads
{
    /** @return array<string, string> threadKey => leadId */
    public function forTenant(string $tenantId): array;

    /** Le fil du DERNIER envoi abouti de la piste (une relance repart dedans). */
    public function latestForLead(string $tenantId, string $leadId): ?string;
}
