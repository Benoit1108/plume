<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Command\RecordReply;

use App\Shared\Application\Command\Command;

final class RecordReply implements Command
{
    public function __construct(
        public readonly string $leadId,
        public readonly ?string $preview = null,
    ) {
    }
}
