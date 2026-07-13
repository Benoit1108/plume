<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Command\CancelFollowUp;

use App\Shared\Application\Command\Command;

final class CancelFollowUp implements Command
{
    public function __construct(public readonly string $leadId)
    {
    }
}
