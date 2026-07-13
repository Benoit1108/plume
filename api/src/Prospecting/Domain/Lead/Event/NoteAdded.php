<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead\Event;

use App\Shared\Domain\AbstractDomainEvent;

final class NoteAdded extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $leadId,
        public readonly string $text,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
