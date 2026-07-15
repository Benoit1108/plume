<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Command\MergeCandidate;

use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Application\IdGenerator;
use App\Shared\Domain\Exception\InvalidValue;
use App\Sourcing\Application\Gateway\DirectoryGateway;
use App\Sourcing\Application\Gateway\ProspectingGateway;
use App\Sourcing\Domain\CandidateLead\CandidateLeadId;
use App\Sourcing\Domain\CandidateLead\CandidateLeadRepository;
use App\Sourcing\Domain\CandidateLead\Exception\CandidateLeadNotFound;

final class MergeCandidateHandler implements CommandHandler
{
    public function __construct(
        private readonly CandidateLeadRepository $candidates,
        private readonly DirectoryGateway $directory,
        private readonly ProspectingGateway $prospecting,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly EventBus $eventBus,
    ) {
    }

    public function __invoke(MergeCandidate $command): void
    {
        $candidate = $this->candidates->find(CandidateLeadId::fromString($command->candidateLeadId))
            ?? throw CandidateLeadNotFound::withId($command->candidateLeadId);

        $tenantId = $candidate->tenantId()->toString();

        if (!$this->directory->organizationExists($command->organizationId, $tenantId)) {
            throw InvalidValue::because(sprintf('Unknown organization "%s".', $command->organizationId));
        }

        $leadId = $this->ids->generate();
        $this->prospecting->createLead(
            $leadId,
            $tenantId,
            $command->organizationId,
            $command->languagePair,
            $candidate->source()->toLeadSource(),
            $command->priority,
            $command->segment,
        );

        $candidate->merge($leadId, $command->organizationId, $this->clock->now());
        $this->candidates->save($candidate);
        $this->eventBus->publish(...$candidate->pullDomainEvents());
    }
}
