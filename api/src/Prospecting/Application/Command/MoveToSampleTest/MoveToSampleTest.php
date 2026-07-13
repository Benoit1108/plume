<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Command\MoveToSampleTest;

use App\Shared\Application\Command\Command;

final class MoveToSampleTest implements Command
{
    public function __construct(public readonly string $leadId)
    {
    }
}
