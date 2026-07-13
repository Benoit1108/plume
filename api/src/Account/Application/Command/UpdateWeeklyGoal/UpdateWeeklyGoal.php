<?php

declare(strict_types=1);

namespace App\Account\Application\Command\UpdateWeeklyGoal;

use App\Shared\Application\Command\Command;

final class UpdateWeeklyGoal implements Command
{
    public function __construct(
        public readonly string $tenantId,
        public readonly int $weeklyGoal,
    ) {
    }
}
