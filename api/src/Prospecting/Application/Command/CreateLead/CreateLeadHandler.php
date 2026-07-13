<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Command\CreateLead;

use App\Prospecting\Application\OrganizationGateway;
use App\Prospecting\Domain\Lead\Exception\ActiveLeadAlreadyExists;
use App\Prospecting\Domain\Lead\Exception\OrganizationNotContactable;
use App\Prospecting\Domain\Lead\Lead;
use App\Prospecting\Domain\Lead\LeadId;
use App\Prospecting\Domain\Lead\LeadRepository;
use App\Prospecting\Domain\Lead\LeadSource;
use App\Prospecting\Domain\Lead\Priority;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\LanguagePair;
use App\Shared\Domain\ValueObject\Segment;
use App\Shared\Domain\ValueObject\TenantId;

final class CreateLeadHandler implements CommandHandler
{
    public function __construct(
        private readonly LeadRepository $leads,
        private readonly OrganizationGateway $organizations,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(CreateLead $command): void
    {
        // Frontière de contexte : l'organisation est vérifiée par le gateway (jamais l'agrégat).
        if (!$this->organizations->exists($command->organizationId)) {
            throw InvalidValue::because(sprintf('Unknown organization "%s".', $command->organizationId));
        }
        if (!$this->organizations->isContactAllowed($command->organizationId)) {
            throw OrganizationNotContactable::forOrganization($command->organizationId);
        }
        if (null !== $command->contactId && !$this->organizations->hasContact($command->organizationId, $command->contactId)) {
            throw InvalidValue::because(sprintf('Unknown contact "%s" in this organization.', $command->contactId));
        }
        // Une seule piste active par organisation (décision M1.2 n°1) — l'index partiel
        // en base sert de filet contre les créations concurrentes.
        if ($this->leads->hasActiveForOrganization($command->organizationId)) {
            throw ActiveLeadAlreadyExists::forOrganization($command->organizationId);
        }

        $lead = Lead::create(
            LeadId::fromString($command->id),
            TenantId::fromString($command->tenantId),
            $command->organizationId,
            $command->contactId,
            LanguagePair::fromString($command->languagePair),
            LeadSource::tryFrom($command->source) ?? throw InvalidValue::because(sprintf('Unknown lead source "%s".', $command->source)),
            Priority::tryFrom($command->priority) ?? throw InvalidValue::because(sprintf('Unknown priority "%s".', $command->priority)),
            Segment::tryFrom($command->segment) ?? throw InvalidValue::because(sprintf('Unknown segment "%s".', $command->segment)),
            $this->clock->now(),
        );

        $this->leads->save($lead);
        $this->eventBus->publish(...$lead->pullDomainEvents());
    }
}
