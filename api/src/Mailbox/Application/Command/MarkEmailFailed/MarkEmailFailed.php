<?php

declare(strict_types=1);

namespace App\Mailbox\Application\Command\MarkEmailFailed;

use App\Shared\Application\Command\Command;

final class MarkEmailFailed implements Command
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $messageId,
        public readonly string $reason,
    ) {
    }
}
