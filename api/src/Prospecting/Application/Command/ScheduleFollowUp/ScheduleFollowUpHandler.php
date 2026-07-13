<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Command\ScheduleFollowUp;

use App\Prospecting\Domain\Lead\LeadId;
use App\Prospecting\Domain\Lead\LeadRepository;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\Exception\InvalidValue;

final class ScheduleFollowUpHandler implements CommandHandler
{
    public function __construct(
        private readonly LeadRepository $leads,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(ScheduleFollowUp $command): void
    {
        $dueAt = \DateTimeImmutable::createFromFormat('!Y-m-d', $command->dueAt);
        if (false === $dueAt) {
            throw InvalidValue::because(sprintf('Invalid follow-up date "%s" (expected Y-m-d).', $command->dueAt));
        }

        $lead = $this->leads->get(LeadId::fromString($command->leadId));
        $lead->scheduleFollowUp($dueAt, $command->label, $this->clock->now());
        $this->leads->save($lead);
        $this->eventBus->publish(...$lead->pullDomainEvents());
    }
}
