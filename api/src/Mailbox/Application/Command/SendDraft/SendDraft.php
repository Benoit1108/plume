<?php

declare(strict_types=1);

namespace App\Mailbox\Application\Command\SendDraft;

use App\Shared\Application\Command\Command;

final class SendDraft implements Command
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $tenantId,
        public readonly string $draftId,
    ) {
    }
}
