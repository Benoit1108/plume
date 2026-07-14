<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Outbound\Event;

use App\Shared\Domain\AbstractDomainEvent;

/** Event riche : la Prospection avance la piste (D3) et le journal trace, sans relecture. */
final class EmailSent extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $messageId,
        public readonly string $leadId,
        public readonly string $draftType,
        public readonly string $threadKey,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
