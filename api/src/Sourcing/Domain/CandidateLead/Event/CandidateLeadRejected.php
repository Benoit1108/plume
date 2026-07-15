<?php

declare(strict_types=1);

namespace App\Sourcing\Domain\CandidateLead\Event;

use App\Shared\Domain\AbstractDomainEvent;

/** Candidate écartée (son empreinte reste, anti-réapparition). */
final class CandidateLeadRejected extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $candidateLeadId,
        public readonly string $tenantId,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
