<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Scheduler;

use App\Shared\Application\Clock;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * RGPD — purge physique des comptes en soft-delete (V2.0-a2). Un compte dont la suppression a été
 * demandée (`app_user.deletion_requested_at`) est effacé DÉFINITIVEMENT après un délai de grâce
 * (30 j) : ce délai laisse un filet contre l'erreur/le regret et s'aligne sur les sauvegardes.
 *
 * Tâche de maintenance GLOBALE, exécutée par le scheduler (rôle propriétaire `plume` → contourne la
 * RLS, seul chemin cross-tenant légitime, ADR-0023). Pour chaque compte expiré, on efface TOUTES
 * les tables portant `tenant_id` (découvertes dynamiquement dans `pg_class` — aucune table oubliée
 * quand le schéma grandit, même philosophie que le garde-fou de couverture RLS), puis les refresh
 * tokens de l'email et enfin l'`app_user`, le tout dans UNE transaction par compte (tout ou rien).
 *
 * Le design est sans clé étrangère inter-tables (références par ID) → l'ordre de suppression est libre.
 * Limite connue (backlog) : la révocation OAuth CÔTÉ FOURNISSEUR n'est pas faite ici ; les tokens
 * chiffrés sont détruits (on ne peut plus accéder à la boîte), la révocation distante reste à ajouter.
 */
#[AsMessageHandler]
final class PurgeDeletedAccountsHandler
{
    private const string GRACE_PERIOD = '-30 days';

    public function __construct(
        private readonly Connection $connection,
        private readonly Clock $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(PurgeDeletedAccountsTick $tick): void
    {
        $cutoff = $this->clock->now()->modify(self::GRACE_PERIOD)->format('Y-m-d H:i:s');

        /** @var list<array{tenant_id: string, email: string}> $expired */
        $expired = $this->connection->fetchAllAssociative(
            'SELECT tenant_id, email FROM app_user
             WHERE deletion_requested_at IS NOT NULL AND deletion_requested_at < :cutoff',
            ['cutoff' => $cutoff],
        );

        if ([] === $expired) {
            return;
        }

        $tenantTables = $this->tenantScopedTables();

        foreach ($expired as $account) {
            $this->connection->transactional(function (Connection $c) use ($account, $tenantTables): void {
                foreach ($tenantTables as $table) {
                    $c->executeStatement(
                        \sprintf('DELETE FROM %s WHERE tenant_id = :tenant', $c->quoteIdentifier($table)),
                        ['tenant' => $account['tenant_id']],
                    );
                }
                $c->executeStatement('DELETE FROM refresh_tokens WHERE username = :email', ['email' => $account['email']]);
                $c->executeStatement('DELETE FROM app_user WHERE tenant_id = :tenant', ['tenant' => $account['tenant_id']]);
            });

            // Traçabilité RGPD (sans PII : on ne journalise que l'identifiant technique du tenant).
            $this->logger->info('Purged deleted account after grace period.', ['tenant_id' => $account['tenant_id']]);
        }
    }

    /**
     * Toutes les tables applicatives portant une colonne `tenant_id` (hors `app_user`, effacée à part).
     *
     * @return list<string>
     */
    private function tenantScopedTables(): array
    {
        /** @var list<string> $tables */
        $tables = $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT c.relname
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'public'
                  AND c.relkind = 'r'
                  AND c.relname <> 'app_user'
                  AND EXISTS (
                      SELECT 1 FROM information_schema.columns col
                      WHERE col.table_schema = 'public' AND col.table_name = c.relname AND col.column_name = 'tenant_id'
                  )
                SQL,
        );

        return $tables;
    }
}
