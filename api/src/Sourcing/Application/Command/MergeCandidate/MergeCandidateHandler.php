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
use App\Sourcing\Domain\CandidateLead\CandidateStatus;
use App\Sourcing\Domain\CandidateLead\Exception\CandidateAlreadyTriaged;
use App\Sourcing\Domain\CandidateLead\Exception\CandidateLeadNotFound;

/**
 * Fusion : rattache une candidate à une organisation EXISTANTE. Si l'organisation
 * a déjà une piste active (cas nominal du dédoublonnage), on RATTACHE à cette piste
 * (note « annonce rattachée ») plutôt que d'en créer une seconde — l'invariant
 * « une piste active par organisation » (M1.2) est ainsi respecté sans erreur.
 */
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

        // Garde de tri AVANT tout effet cross-contexte (défense de domaine, pas seulement
        // l'atomicité émergente du bus) : un re-tri concurrent ne crée ni org ni piste.
        if (CandidateStatus::PENDING !== $candidate->status()) {
            throw CandidateAlreadyTriaged::is($candidate->status());
        }

        $tenantId = $candidate->tenantId()->toString();

        if (!$this->directory->organizationExists($command->organizationId, $tenantId)) {
            throw InvalidValue::because(sprintf('Unknown organization "%s".', $command->organizationId));
        }

        $existingLeadId = $this->prospecting->activeLeadId($tenantId, $command->organizationId);
        if (null !== $existingLeadId) {
            // Rattachement à la piste active existante — pas de seconde piste.
            $this->prospecting->annotateLead($existingLeadId, sprintf('Annonce rattachée : %s', $candidate->title()));
            $leadId = $existingLeadId;
        } else {
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
        }

        $candidate->merge($leadId, $command->organizationId, $this->clock->now());
        $this->candidates->save($candidate);
        $this->eventBus->publish(...$candidate->pullDomainEvents());
    }
}
