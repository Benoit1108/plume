<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead\Event;

use App\Shared\Domain\AbstractDomainEvent;

final class LeadPaused extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $leadId,
        public readonly string $pausedFrom,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
