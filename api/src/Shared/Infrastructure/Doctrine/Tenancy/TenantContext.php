<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Tenancy;

use App\Shared\Domain\ValueObject\TenantId;

/**
 * Porte le tenant courant (extrait du JWT à chaque requête).
 * Sert à activer/paramétrer le TenantFilter Doctrine.
 */
final class TenantContext
{
    private ?TenantId $tenantId = null;

    public function set(TenantId $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function get(): ?TenantId
    {
        return $this->tenantId;
    }
}
