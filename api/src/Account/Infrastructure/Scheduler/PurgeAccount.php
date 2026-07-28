<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Scheduler;

/**
 * Ordre de purge PHYSIQUE d'UN compte expiré (RGPD, V2.0-a2). Émis par le fan-out
 * PurgeDeletedAccountsHandler ; traité sur le command.bus (donc UNE transaction par compte —
 * atomicité et isolation de panne réelles, contrairement à une boucle imbriquée). Porte `tenantId`
 * (convention TenantIsolationMiddleware → tenant activé, RLS appliquée sur les tables tenantées).
 */
final class PurgeAccount
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $email,
    ) {
    }
}
