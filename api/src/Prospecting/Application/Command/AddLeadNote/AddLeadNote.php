<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Command\AddLeadNote;

use App\Shared\Application\Command\Command;

final class AddLeadNote implements Command
{
    public function __construct(
        public readonly string $leadId,
        public readonly string $text,
    ) {
    }
}
