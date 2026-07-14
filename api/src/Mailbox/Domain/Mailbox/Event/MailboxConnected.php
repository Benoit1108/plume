<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Mailbox\Event;

use App\Shared\Domain\AbstractDomainEvent;

final class MailboxConnected extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $mailboxId,
        public readonly string $provider,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
