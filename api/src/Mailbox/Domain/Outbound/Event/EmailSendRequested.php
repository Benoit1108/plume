<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Outbound\Event;

use App\Shared\Domain\AbstractDomainEvent;

/** Routé ASYNC : le worker fait l'appel provider. */
final class EmailSendRequested extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $messageId,
        public readonly string $draftId,
        public readonly string $leadId,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
