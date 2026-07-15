<?php

declare(strict_types=1);

namespace App\Sourcing\Domain\CandidateLead\Event;

use App\Shared\Domain\AbstractDomainEvent;

/** Une annonce est entrée dans la file de tri. */
final class CandidateLeadIngested extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $candidateLeadId,
        public readonly string $tenantId,
        public readonly string $source,
        public readonly string $dedupHash,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
