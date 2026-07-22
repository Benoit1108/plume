<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Tenancy;

use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Point UNIQUE d'entrée/sortie du tenant courant : synchronise le `TenantContext` ET le
 * SQLFilter Doctrine. Utilisé aussi bien en HTTP (listener JWT) qu'en worker (politiques,
 * middleware Messenger) → l'isolation est fail-closed **symétrique** entre les deux mondes.
 */
final class TenantScope
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** Active le tenant : contexte + filtre Doctrine paramétré (toute lecture ORM est scopée). */
    public function activate(TenantId $tenantId): void
    {
        $this->context->set($tenantId);
        $this->em->getFilters()->enable('tenant')->setParameter('tenant_id', $tenantId->toString());
    }

    /** Remet à zéro : contexte vidé + filtre désactivé (anti-fuite entre requêtes/messages). */
    public function clear(): void
    {
        $this->context->clear();
        $filters = $this->em->getFilters();
        if ($filters->isEnabled('tenant')) {
            $filters->disable('tenant');
        }
    }
}
