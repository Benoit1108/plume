<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Mailbox\Event;

use App\Shared\Domain\AbstractDomainEvent;

final class MailboxSyncFailed extends AbstractDomainEvent
{
    /** @param string $reason code stable affichable (i18n front), jamais un message interne */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $mailboxId,
        public readonly string $reason,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
