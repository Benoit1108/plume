<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Command\RecordFollowUp;

use App\Shared\Application\Command\Command;

final class RecordFollowUp implements Command
{
    public function __construct(public readonly string $leadId)
    {
    }
}
