<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Command\ResumeLead;

use App\Shared\Application\Command\Command;

final class ResumeLead implements Command
{
    public function __construct(public readonly string $leadId)
    {
    }
}
