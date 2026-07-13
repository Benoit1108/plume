<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Command\ResumeLead;

use App\Prospecting\Domain\Lead\LeadId;
use App\Prospecting\Domain\Lead\LeadRepository;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;

final class ResumeLeadHandler implements CommandHandler
{
    public function __construct(
        private readonly LeadRepository $leads,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(ResumeLead $command): void
    {
        $lead = $this->leads->get(LeadId::fromString($command->leadId));
        $lead->resume($this->clock->now());
        $this->leads->save($lead);
        $this->eventBus->publish(...$lead->pullDomainEvents());
    }
}
