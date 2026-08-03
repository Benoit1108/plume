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

    public function activate(string $tenantId, string $customerId, string $subscriptionId, ?\DateTimeImmutable $currentPeriodEnd): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO subscription (tenant_id, status, stripe_customer_id, stripe_subscription_id, current_period_end, updated_at)
                VALUES (:tenant, :status, :customer, :subscription, :period_end, :now)
                ON CONFLICT (tenant_id) DO UPDATE SET
                    status = excluded.status,
                    stripe_customer_id = excluded.stripe_customer_id,
                    stripe_subscription_id = excluded.stripe_subscription_id,
                    current_period_end = excluded.current_period_end,
                    updated_at = excluded.updated_at
                SQL,
            [
                'tenant' => $tenantId,
                'status' => SubscriptionStatus::ACTIVE->value,
                'customer' => $customerId,
                'subscription' => $subscriptionId,
                'period_end' => $currentPeriodEnd?->format('Y-m-d H:i:s'),
                'now' => $this->clock->now()->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function applyStatusByCustomer(string $customerId, SubscriptionStatus $status, ?\DateTimeImmutable $currentPeriodEnd): void
    {
        // Transition webhook : on ne crée jamais de ligne ici (le client Stripe naît au checkout).
        $this->connection->executeStatement(
            <<<'SQL'
                UPDATE subscription
                SET status = :status,
                    current_period_end = COALESCE(:period_end, current_period_end),
                    updated_at = :now
                WHERE stripe_customer_id = :customer
                SQL,
            [
                'status' => $status->value,
                'period_end' => $currentPeriodEnd?->format('Y-m-d H:i:s'),
                'now' => $this->clock->now()->format('Y-m-d H:i:s'),
                'customer' => $customerId,
            ],
        );
    }

    public function stripeCustomerFor(string $tenantId): ?string
    {
        $customer = $this->connection->fetchOne(
            'SELECT stripe_customer_id FROM subscription WHERE tenant_id = :tenant',
            ['tenant' => $tenantId],
        );

        return \is_string($customer) && '' !== $customer ? $customer : null;
    }
}
