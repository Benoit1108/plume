<?php

declare(strict_types=1);

namespace App\Drafting\Application\Command\FailDraft;

use App\Shared\Application\Command\Command;

final class FailDraft implements Command
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $draftId,
        public readonly string $reason,
    ) {
    }
}
