<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Scheduler;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * RGPD — purge physique d'UN compte (V2.0-a2, revue V2.0 P1). Traité sur le command.bus : le
 * middleware `doctrine_transaction` ouvre UNE transaction pour ce message → l'effacement de toutes
 * les tables tenantées + refresh tokens + app_user est atomique POUR CE COMPTE, et un échec n'annule
 * QUE ce message (retry/`failed` isolés), sans bloquer les autres comptes. C'est ce que la boucle
 * imbriquée précédente ne garantissait pas (transaction externe du bus → rollback global).
 *
 * Tenant activé par le middleware (message `tenantId`) → les DELETE sur les tables tenantées sont
 * doublement bornés : RLS (rôle runtime) + prédicat explicite `tenant_id`. `app_user`/`refresh_tokens`
 * (hors RLS) sont bornés par prédicat explicite. Tables découvertes dans `pg_class` (aucune oubliée).
 */
#[AsMessageHandler]
final class PurgeAccountHandler
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(PurgeAccount $message): void
    {
        // Pas de transaction explicite ici : le command.bus en ouvre déjà une pour ce message
        // (une par compte). Une exception remonte → rollback de CE message + retry Messenger.
        foreach ($this->tenantScopedTables() as $table) {
            $this->connection->executeStatement(
                \sprintf('DELETE FROM %s WHERE tenant_id = :tenant', $this->connection->quoteIdentifier($table)),
                ['tenant' => $message->tenantId],
            );
        }
        $this->connection->executeStatement('DELETE FROM refresh_tokens WHERE username = :email', ['email' => $message->email]);

        // Best-effort RGPD : un message en ÉCHEC (queue `failed`) peut porter des données du tenant
        // sérialisées — hors RLS, sans tenant_id — et survivrait au délai de grâce. On l'efface par
        // correspondance de l'UUID du tenant dans le corps (les autres files sont consommées en régime
        // nominal ; on ne touche pas la file live pour ne pas percuter un message en cours).
        $this->connection->executeStatement(
            "DELETE FROM messenger_messages WHERE queue_name = 'failed' AND body LIKE :needle",
            ['needle' => '%'.$message->tenantId.'%'],
        );

        $this->connection->executeStatement('DELETE FROM app_user WHERE tenant_id = :tenant', ['tenant' => $message->tenantId]);

        // Traçabilité RGPD (sans PII : seul l'identifiant technique du tenant).
        $this->logger->info('Purged deleted account after grace period.', ['tenant_id' => $message->tenantId]);
    }

    /**
     * Tables applicatives portant `tenant_id` (hors `app_user`, effacée à part).
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
