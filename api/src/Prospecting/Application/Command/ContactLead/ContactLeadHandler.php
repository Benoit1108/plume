<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Command\ContactLead;

use App\Prospecting\Domain\Lead\LeadId;
use App\Prospecting\Domain\Lead\LeadRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;

final class ContactLeadHandler implements CommandHandler
{
    public function __construct(
        private readonly LeadRepository $leads,
        private readonly EventBus $eventBus,
    ) {
    }

    public function __invoke(ContactLead $command): void
    {
        $lead = $this->leads->get(LeadId::fromString($command->leadId));
        $lead->contact(new \DateTimeImmutable());
        $this->leads->save($lead);

        // Outbox : l'INSERT des events dans le transport doctrine rejoint la transaction
        // de la commande (même connexion) ; consommés en asynchrone par le worker.
        $this->eventBus->publish(...$lead->pullDomainEvents());
    }
}
