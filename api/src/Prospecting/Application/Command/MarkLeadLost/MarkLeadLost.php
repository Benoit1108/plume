<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Command\MarkLeadLost;

use App\Shared\Application\Command\Command;

final class MarkLeadLost implements Command
{
    public function __construct(public readonly string $leadId)
    {
    }
}
