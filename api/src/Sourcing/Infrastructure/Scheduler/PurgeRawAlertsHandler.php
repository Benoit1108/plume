<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Scheduler;

use App\Shared\Application\Clock;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Purge du brut (D6, ADR-0021) : une annonce **triée** (REJECTED/ACCEPTED/MERGED) garde son
 * `dedupHash` (anti-réapparition) mais pas son contenu brut au-delà de 30 jours — le brut n'a
 * plus d'utilité une fois le tri fait, et peut contenir des données personnelles de tiers
 * (corps d'email d'alerte). Seules les annonces PENDING conservent leur brut. Tâche de
 * maintenance globale (tous tenants). L'ancienneté est approximée par `ingested_at`.
 */
#[AsMessageHandler]
final class PurgeRawAlertsHandler
{
    private const string RETENTION = '-30 days';

    public function __construct(
        private readonly Connection $connection,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(PurgeRawAlertsTick $tick): void
    {
        $cutoff = $this->clock->now()->modify(self::RETENTION)->format('Y-m-d H:i:s');

        // 1) Supprimer le brut référencé par une annonce TRIÉE (non PENDING) de longue date.
        $this->connection->executeStatement(
            "DELETE FROM raw_alert WHERE id IN (
                SELECT raw_ref FROM candidate_lead
                WHERE status <> 'PENDING' AND raw_ref IS NOT NULL AND ingested_at < :cutoff
            )",
            ['cutoff' => $cutoff],
        );

        // 2) Détacher la référence (le brut n'existe plus) — la candidate et son dedupHash restent.
        $this->connection->executeStatement(
            "UPDATE candidate_lead SET raw_ref = NULL
             WHERE status <> 'PENDING' AND raw_ref IS NOT NULL AND ingested_at < :cutoff",
            ['cutoff' => $cutoff],
        );
    }
}
