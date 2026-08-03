<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Persistence;

use App\Billing\Application\Subscriptions;
use App\Billing\Domain\SubscriptionStatus;
use App\Shared\Application\Clock;
use Doctrine\DBAL\Connection;

/**
 * État d'abonnement en SQL direct (table `subscription`, hors ORM / hors RLS — toujours filtrée par
 * `tenant_id` explicite, comme `app_user`). Écrite à l'inscription (essai) et par Stripe (webhooks,
 * slice 2). Lecture consultée par la garde « lecture seule » à chaque écriture produit.
 */
final class DoctrineSubscriptions implements Subscriptions
{
    private const int TRIAL_DAYS = 14;

    public function __construct(
        private readonly Connection $connection,
        private readonly Clock $clock,
    ) {
    }

    public function startTrial(string $tenantId): void
    {
        $now = $this->clock->now();
        // Idempotent : une inscription rejouée ne redémarre pas l'essai (ON CONFLICT DO NOTHING).
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO subscription (tenant_id, status, trial_ends_at, updated_at)
                VALUES (:tenant, :status, :trial_ends_at, :now)
                ON CONFLICT (tenant_id) DO NOTHING
                SQL,
            [
                'tenant' => $tenantId,
                'status' => SubscriptionStatus::TRIALING->value,
                'trial_ends_at' => $now->modify('+'.self::TRIAL_DAYS.' days')->format('Y-m-d H:i:s'),
                'now' => $now->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function isEntitled(string $tenantId): bool
    {
        $row = $this->connection->fetchAssociative(
            'SELECT status, trial_ends_at FROM subscription WHERE tenant_id = :tenant',
            ['tenant' => $tenantId],
        );

        // Aucun abonnement (compte antérieur à la facturation) → accès complet (grandfathered).
        if (false === $row) {
            return true;
        }

        $status = SubscriptionStatus::tryFrom(\is_string($row['status'] ?? null) ? $row['status'] : '');
        if (null === $status) {
            return true; // statut inconnu : on n'enferme pas (fail-open, comme le budget IA)
        }

        $trialStillValid = \is_string($row['trial_ends_at'] ?? null)
            && new \DateTimeImmutable($row['trial_ends_at']) > $this->clock->now();

        return $status->grantsWriteAccess($trialStillValid);
    }
}
