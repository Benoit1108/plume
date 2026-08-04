<?php

declare(strict_types=1);

namespace App\Account\Domain\Profile\Event;

use App\Shared\Domain\AbstractDomainEvent;

final class WeeklyReportPreferenceChanged extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $tenantId,
        public readonly bool $enabled,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
