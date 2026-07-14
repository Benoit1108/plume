<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Outbound\Event;

use App\Shared\Domain\AbstractDomainEvent;

final class EmailSendFailed extends AbstractDomainEvent
{
    /** @param string $reason code stable affichable (i18n front) */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $messageId,
        public readonly string $leadId,
        public readonly string $reason,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
