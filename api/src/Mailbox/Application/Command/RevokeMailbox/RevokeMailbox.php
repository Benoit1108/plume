<?php

declare(strict_types=1);

namespace App\Mailbox\Application\Command\RevokeMailbox;

use App\Shared\Application\Command\Command;

final class RevokeMailbox implements Command
{
    public function __construct(public readonly string $tenantId)
    {
    }
}
