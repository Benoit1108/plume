<?php

declare(strict_types=1);

namespace App\Sourcing\Domain\RawAlert;

use App\Shared\Domain\ValueObject\TenantId;
use App\Sourcing\Domain\CandidateLead\Source;

/**
 * Contenu BRUT d'une annonce ingérée (item RSS, plus tard email), conservé pour
 * audit / reprocessing. Support hors agrégat métier : aucune règle, aucun event —
 * la `CandidateLead` le référence par `rawRef`. Purge planifiée (D6) : M3.1b.
 */
final class RawAlert
{
    private function __construct(
        private readonly RawAlertId $id,
        private readonly TenantId $tenantId,
        private readonly Source $source,
        private readonly string $payload,
        private readonly \DateTimeImmutable $fetchedAt,
    ) {
    }

    public static function capture(
        RawAlertId $id,
        TenantId $tenantId,
        Source $source,
        string $payload,
        \DateTimeImmutable $now,
    ): self {
        return new self($id, $tenantId, $source, $payload, $now);
    }

    public function id(): RawAlertId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function source(): Source
    {
        return $this->source;
    }

    public function payload(): string
    {
        return $this->payload;
    }

    public function fetchedAt(): \DateTimeImmutable
    {
        return $this->fetchedAt;
    }
}
