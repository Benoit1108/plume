<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Command\IngestCandidate;

use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Application\IdGenerator;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\TenantId;
use App\Sourcing\Domain\CandidateLead\CandidateLead;
use App\Sourcing\Domain\CandidateLead\CandidateLeadId;
use App\Sourcing\Domain\CandidateLead\CandidateLeadRepository;
use App\Sourcing\Domain\CandidateLead\Dedup;
use App\Sourcing\Domain\CandidateLead\Source;
use App\Sourcing\Domain\RawAlert\RawAlert;
use App\Sourcing\Domain\RawAlert\RawAlertId;
use App\Sourcing\Domain\RawAlert\RawAlertRepository;

final class IngestCandidateHandler implements CommandHandler
{
    public function __construct(
        private readonly CandidateLeadRepository $candidates,
        private readonly RawAlertRepository $rawAlerts,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly EventBus $eventBus,
    ) {
    }

    public function __invoke(IngestCandidate $command): void
    {
        $tenantId = TenantId::fromString($command->tenantId);
        $source = Source::tryFrom($command->source)
            ?? throw InvalidValue::because(sprintf('Unknown source "%s".', $command->source));

        $dedupHash = Dedup::hash($source, $command->externalId, $command->organizationName, $command->title);

        // Anti-doublon (ADR-0021) : déjà vue → no-op silencieux (aucun brut conservé).
        if ($this->candidates->existsByDedupHash($tenantId, $dedupHash)) {
            return;
        }

        $now = $this->clock->now();

        // Conservation du brut (audit / reprocessing) — seulement pour une annonce neuve.
        $rawRef = null;
        if (null !== $command->rawPayload && '' !== trim($command->rawPayload)) {
            $rawAlert = RawAlert::capture(
                RawAlertId::fromString($this->ids->generate()),
                $tenantId,
                $source,
                $command->rawPayload,
                $now,
            );
            $this->rawAlerts->save($rawAlert);
            $rawRef = $rawAlert->id()->toString();
        }

        $candidate = CandidateLead::ingest(
            CandidateLeadId::fromString($this->ids->generate()),
            $tenantId,
            $source,
            $dedupHash,
            $command->title,
            $command->organizationName,
            $command->languagePair,
            $command->url,
            $command->excerpt,
            self::parseDate($command->postedAt),
            $now,
            $rawRef,
        );

        $this->candidates->save($candidate);
        $this->eventBus->publish(...$candidate->pullDomainEvents());
    }

    private static function parseDate(?string $iso): ?\DateTimeImmutable
    {
        if (null === $iso || '' === trim($iso)) {
            return null;
        }
        try {
            return new \DateTimeImmutable($iso);
        } catch (\Exception) {
            return null;
        }
    }
}
