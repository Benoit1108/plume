<?php

declare(strict_types=1);

namespace App\Sourcing\Domain\CandidateLead;

use App\Shared\Domain\ValueObject\TenantId;

interface CandidateLeadRepository
{
    public function save(CandidateLead $candidate): void;

    public function find(CandidateLeadId $id): ?CandidateLead;

    /** Anti-doublon à l'ingestion (ADR-0021) — tenant explicite (ingestion asynchrone). */
    public function existsByDedupHash(TenantId $tenantId, string $dedupHash): bool;
}
