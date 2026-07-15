<?php

declare(strict_types=1);

namespace App\Sourcing\Domain\CandidateLead\Event;

use App\Shared\Domain\AbstractDomainEvent;

/** Candidate rattachée à une organisation existante + piste créée. */
final class CandidateLeadMerged extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $candidateLeadId,
        public readonly string $tenantId,
        public readonly string $leadId,
        public readonly string $organizationId,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
