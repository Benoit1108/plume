<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\Generator;

use App\Drafting\Application\AiGenerationPolicy;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Doctrine\DBAL\Connection;

/**
 * Refuse la génération PAYANTE aux tenants de DÉMONSTRATION (colonne `app_user.demo_expires_at`
 * renseignée). La génération tourne en asynchrone (worker), où le tenant est déjà activé
 * (`TenantContext`) : on relit donc le statut démo en base plutôt que de véhiculer un drapeau
 * depuis la requête HTTP jusque dans le domaine. Hors tenant (CLI/seed) : pas de restriction.
 * Lecture DBAL cross-contexte tolérée (Infrastructure ; `app_user` hors RLS, filtrée par tenant).
 */
final class TenantAiGenerationPolicy implements AiGenerationPolicy
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function allowsPaidGeneration(): bool
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            return true;
        }

        $isDemo = $this->connection->fetchOne(
            'SELECT 1 FROM app_user WHERE tenant_id = :tenant AND demo_expires_at IS NOT NULL',
            ['tenant' => $tenant->toString()],
        );

        return false === $isDemo;
    }
}
