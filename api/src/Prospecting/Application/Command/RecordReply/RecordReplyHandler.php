<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Command\RecordReply;

use App\Prospecting\Domain\Lead\Exception\LeadNotFound;
use App\Prospecting\Domain\Lead\LeadId;
use App\Prospecting\Domain\Lead\LeadRepository;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;

final class RecordReplyHandler implements CommandHandler
{
    public function __construct(
        private readonly LeadRepository $leads,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(RecordReply $command): void
    {
        $lead = $this->leads->get(LeadId::fromString($command->leadId));
        // Ceinture-bretelles worker (SQLFilter inactif hors HTTP) : si la commande
        // porte un tenant, il DOIT être celui de la piste — sinon, introuvable.
        if (null !== $command->tenantId && $lead->tenantId()->toString() !== $command->tenantId) {
            throw LeadNotFound::withId($lead->id());
        }
        $lead->recordReply($this->clock->now(), $command->preview);
        $this->leads->save($lead);
        $this->eventBus->publish(...$lead->pullDomainEvents());
    }
}
