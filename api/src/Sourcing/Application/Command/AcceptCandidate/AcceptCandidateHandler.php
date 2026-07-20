<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Command\AcceptCandidate;

use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Application\IdGenerator;
use App\Sourcing\Application\Gateway\DirectoryGateway;
use App\Sourcing\Application\Gateway\ProspectingGateway;
use App\Sourcing\Domain\CandidateLead\CandidateLeadId;
use App\Sourcing\Domain\CandidateLead\CandidateLeadRepository;
use App\Sourcing\Domain\CandidateLead\CandidateStatus;
use App\Sourcing\Domain\CandidateLead\Exception\CandidateAlreadyTriaged;
use App\Sourcing\Domain\CandidateLead\Exception\CandidateLeadNotFound;

/**
 * Promotion (ADR-0020) : crée l'organisation puis la piste (par gateways), enfin
 * marque la candidate ACCEPTED. En cas d'échec partiel, la candidate reste PENDING
 * (rejouable) ; l'unicité du nom d'organisation empêche un doublon au rejeu.
 */
final class AcceptCandidateHandler implements CommandHandler
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

    public function __invoke(AcceptCandidate $command): void
    {
        $candidate = $this->candidates->find(CandidateLeadId::fromString($command->candidateLeadId))
            ?? throw CandidateLeadNotFound::withId($command->candidateLeadId);

        // Garde de tri AVANT tout effet cross-contexte : un re-tri concurrent ne crée ni org ni piste.
        if (CandidateStatus::PENDING !== $candidate->status()) {
            throw CandidateAlreadyTriaged::is($candidate->status());
        }

        $tenantId = $candidate->tenantId()->toString();

        $organizationId = $this->ids->generate();
        $this->directory->createOrganization(
            $organizationId,
            $tenantId,
            $command->organizationName,
            $command->organizationType,
            $command->website,
            [$command->segment],
        );

        $leadId = $this->ids->generate();
        $this->prospecting->createLead(
            $leadId,
            $tenantId,
            $organizationId,
            $command->languagePair,
            $candidate->source()->toLeadSource(),
            $command->priority,
            $command->segment,
        );

        $candidate->accept($leadId, $organizationId, $this->clock->now());
        $this->candidates->save($candidate);
        $this->eventBus->publish(...$candidate->pullDomainEvents());
    }
}
