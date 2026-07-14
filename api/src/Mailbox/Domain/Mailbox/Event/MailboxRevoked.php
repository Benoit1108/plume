<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Mailbox\Event;

use App\Shared\Domain\AbstractDomainEvent;

final class MailboxRevoked extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $mailboxId,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
