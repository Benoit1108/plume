<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

/**
 * Porte l'identifiant de corrélation de la requête courante (un par requête HTTP, propagé aux
 * messages async via {@see \App\Shared\Infrastructure\Messenger\CorrelationStamp}). Permet de
 * regrouper TOUTES les lignes de log — HTTP puis traitement worker déclenché par cette requête —
 * sous un même `request_id`. Symétrique de {@see \App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext}.
 */
final class CorrelationContext
{
    private ?string $requestId = null;

    public function set(string $requestId): void
    {
        $this->requestId = $requestId;
    }

    public function clear(): void
    {
        $this->requestId = null;
    }

    public function get(): ?string
    {
        return $this->requestId;
    }
}
