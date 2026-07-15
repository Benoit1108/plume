<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Domain\ValueObject\TenantId;
use App\Sourcing\Domain\CandidateLead\CandidateLead;
use App\Sourcing\Domain\CandidateLead\CandidateLeadId;
use App\Sourcing\Domain\CandidateLead\CandidateLeadRepository;

final class InMemoryCandidateLeadRepository implements CandidateLeadRepository
{
    /** @var array<string, CandidateLead> */
    private array $byId = [];

    public function save(CandidateLead $candidate): void
    {
        $this->byId[$candidate->id()->toString()] = $candidate;
    }

    public function find(CandidateLeadId $id): ?CandidateLead
    {
        return $this->byId[$id->toString()] ?? null;
    }

    public function existsByDedupHash(TenantId $tenantId, string $dedupHash): bool
    {
        foreach ($this->byId as $candidate) {
            if ($candidate->tenantId()->equals($tenantId) && $candidate->dedupHash() === $dedupHash) {
                return true;
            }
        }

        return false;
    }

    public function count(): int
    {
        return \count($this->byId);
    }
}
