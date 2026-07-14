<?php

declare(strict_types=1);

namespace App\Mailbox\Application\Command\MarkEmailSent;

use App\Shared\Application\Command\Command;

final class MarkEmailSent implements Command
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $messageId,
        public readonly string $threadKey,
    ) {
    }
}
