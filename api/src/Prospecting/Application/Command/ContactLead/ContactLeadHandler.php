<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Command\ContactLead;

use App\Prospecting\Application\OrganizationGateway;
use App\Prospecting\Domain\Lead\Exception\LeadNotFound;
use App\Prospecting\Domain\Lead\Exception\OrganizationNotContactable;
use App\Prospecting\Domain\Lead\LeadId;
use App\Prospecting\Domain\Lead\LeadRepository;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;

final class ContactLeadHandler implements CommandHandler
{
    public function __construct(
        private readonly LeadRepository $leads,
        private readonly OrganizationGateway $organizations,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(ContactLead $command): void
    {
        $lead = $this->leads->get(LeadId::fromString($command->leadId));
        // Ceinture-bretelles worker (SQLFilter inactif hors HTTP) : si la commande
        // porte un tenant, il DOIT être celui de la piste — sinon, introuvable.
        if (null !== $command->tenantId && $lead->tenantId()->toString() !== $command->tenantId) {
            throw LeadNotFound::withId($lead->id());
        }

        // Garde RGPD : une organisation « ne pas contacter » ne se démarche pas,
        // même si la piste existait avant le marquage.
        if (!$this->organizations->isContactAllowed($lead->organizationId())) {
            throw OrganizationNotContactable::forOrganization($lead->organizationId());
        }

        $lead->contact($this->clock->now());
        $this->leads->save($lead);

        // Outbox : l'INSERT des events dans le transport doctrine rejoint la transaction
        // de la commande (même connexion) ; consommés en asynchrone par le worker.
        $this->eventBus->publish(...$lead->pullDomainEvents());
    }
}
