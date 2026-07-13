<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Command\PauseLead;

use App\Shared\Application\Command\Command;

final class PauseLead implements Command
{
    public function __construct(public readonly string $leadId)
    {
    }
}
