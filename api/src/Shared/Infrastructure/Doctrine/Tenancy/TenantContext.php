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

    /** Remet à zéro le tenant courant (fin de requête / de message worker — anti-fuite). */
    public function clear(): void
    {
        $this->tenantId = null;
    }

    public function get(): ?TenantId
    {
        return $this->tenantId;
    }

    /**
     * FAIL-CLOSED : à utiliser par tout lecteur/écrivain SQL direct — jamais de
     * requête non scopée par accident. Hors HTTP (worker, CLI), le tenant doit
     * venir de la commande ou de l'event, pas d'ici.
     */
    public function require(): TenantId
    {
        return $this->tenantId
            ?? throw new \LogicException('No tenant in context — refusing to run an unscoped operation.');
    }
}
