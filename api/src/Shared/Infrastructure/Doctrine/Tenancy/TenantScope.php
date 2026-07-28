<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Tenancy;

use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Point UNIQUE d'entrée/sortie du tenant courant : synchronise le `TenantContext`, le SQLFilter
 * Doctrine (couche applicative) ET la variable de session Postgres `app.current_tenant` (base,
 * lue par les policies RLS). Utilisé en HTTP (listener JWT), en fin de requête (terminate) et
 * en worker (middleware Messenger) → isolation fail-closed **symétrique** partout.
 *
 * La variable est posée en session (persiste sur la connexion réutilisée par FrankenPHP) : elle
 * DOIT donc être réinitialisée en fin de requête/message — d'où `clear()`.
 */
final class TenantScope
{
    private const string RLS_VAR = 'app.current_tenant';

    public function __construct(
        private readonly TenantContext $context,
        private readonly EntityManagerInterface $em,
        private readonly Connection $connection,
    ) {
    }

    /** Active le tenant : contexte + filtre Doctrine paramétré + variable de session RLS. */
    public function activate(TenantId $tenantId): void
    {
        $this->context->set($tenantId);
        $this->em->getFilters()->enable('tenant')->setParameter('tenant_id', $tenantId->toString());
        $this->setSessionTenant($tenantId->toString());
    }

    /**
     * Remet à zéro : contexte vidé + filtre désactivé + variable RLS effacée (fail-closed).
     *
     * La variable de session n'est réinitialisée QUE si la connexion est déjà ouverte : inutile
     * d'OUVRIR une connexion juste pour l'effacer sur une requête non tenantée (/docs, health-check,
     * préflight) — une connexion fraîche démarre sans `app.current_tenant`. Sur une connexion réutilisée
     * (FrankenPHP), elle est bien ouverte → on efface l'ardoise du tenant précédent (anti-fuite).
     */
    public function clear(): void
    {
        $this->context->clear();
        $filters = $this->em->getFilters();
        if ($filters->isEnabled('tenant')) {
            $filters->disable('tenant');
        }
        if ($this->connection->isConnected()) {
            $this->setSessionTenant('');
        }
    }

    private function setSessionTenant(string $value): void
    {
        // set_config(is_local=false) : niveau session, lisible par current_setting() dans les
        // policies RLS, y compris hors transaction (lectures des read models).
        $this->connection->executeStatement('SELECT set_config(?, ?, false)', [self::RLS_VAR, $value]);
    }
}
