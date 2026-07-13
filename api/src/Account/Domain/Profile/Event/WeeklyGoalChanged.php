<?php

declare(strict_types=1);

namespace App\Account\Domain\Profile\Event;

use App\Shared\Domain\AbstractDomainEvent;

final class WeeklyGoalChanged extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $tenantId,
        public readonly int $weeklyGoal,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
