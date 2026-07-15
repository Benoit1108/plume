<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Command\RejectCandidate;

use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Sourcing\Domain\CandidateLead\CandidateLeadId;
use App\Sourcing\Domain\CandidateLead\CandidateLeadRepository;
use App\Sourcing\Domain\CandidateLead\Exception\CandidateLeadNotFound;

final class RejectCandidateHandler implements CommandHandler
{
    public function __construct(
        private readonly CandidateLeadRepository $candidates,
        private readonly Clock $clock,
        private readonly EventBus $eventBus,
    ) {
    }

    public function __invoke(RejectCandidate $command): void
    {
        $candidate = $this->candidates->find(CandidateLeadId::fromString($command->candidateLeadId))
            ?? throw CandidateLeadNotFound::withId($command->candidateLeadId);

        $candidate->reject($this->clock->now());
        $this->candidates->save($candidate);
        $this->eventBus->publish(...$candidate->pullDomainEvents());
    }
}
