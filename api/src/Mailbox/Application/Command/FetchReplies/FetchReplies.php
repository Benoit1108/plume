<?php

declare(strict_types=1);

namespace App\Mailbox\Application\Command\FetchReplies;

use App\Shared\Application\Command\Command;

/** Relève des réponses pour LA boîte du tenant (Scheduler ou geste manuel). */
final class FetchReplies implements Command
{
    public function __construct(public readonly string $tenantId)
    {
    }
}
