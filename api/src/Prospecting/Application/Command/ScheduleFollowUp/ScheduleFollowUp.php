<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Command\ScheduleFollowUp;

use App\Shared\Application\Command\Command;

final class ScheduleFollowUp implements Command
{
    public function __construct(
        public readonly string $leadId,
        public readonly string $dueAt,
        public readonly ?string $label,
    ) {
    }
}
