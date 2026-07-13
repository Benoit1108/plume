<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Command\MarkLeadWon;

use App\Shared\Application\Command\Command;

final class MarkLeadWon implements Command
{
    public function __construct(public readonly string $leadId)
    {
    }
}
