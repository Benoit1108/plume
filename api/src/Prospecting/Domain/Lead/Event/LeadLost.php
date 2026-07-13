<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead\Event;

use App\Shared\Domain\AbstractDomainEvent;

final class LeadLost extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $leadId,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
