<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Command\PauseLead;

use App\Prospecting\Domain\Lead\LeadId;
use App\Prospecting\Domain\Lead\LeadRepository;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;

final class PauseLeadHandler implements CommandHandler
{
    public function __construct(
        private readonly LeadRepository $leads,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(PauseLead $command): void
    {
        $lead = $this->leads->get(LeadId::fromString($command->leadId));
        $lead->pause($this->clock->now());
        $this->leads->save($lead);
        $this->eventBus->publish(...$lead->pullDomainEvents());
    }
}
