<?php

declare(strict_types=1);

namespace App\Account\Domain\Profile\Event;

use App\Shared\Domain\AbstractDomainEvent;

final class FollowUpCadenceChanged extends AbstractDomainEvent
{
    /** @param int[] $days */
    public function __construct(
        public readonly string $tenantId,
        public readonly array $days,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
