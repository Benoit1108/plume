<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Command\ContactLead;

use App\Prospecting\Domain\Lead\LeadId;
use App\Prospecting\Domain\Lead\LeadRepository;
use App\Shared\Application\Event\EventBus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class ContactLeadHandler
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

        // Publiés après commit (dispatch_after_current_bus), consommés en asynchrone.
        $this->eventBus->publish(...$lead->pullDomainEvents());
    }
}
