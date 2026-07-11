<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead\Event;

use App\Shared\Domain\DomainEvent;

final class LeadCreated implements DomainEvent
{
    public function __construct(
        public readonly string $leadId,
        private readonly \DateTimeImmutable $occurredOn,
    ) {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
