<?php

declare(strict_types=1);

namespace App\Account\Domain\Profile\Event;

use App\Shared\Domain\AbstractDomainEvent;

final class ProfileCreated extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $tenantId,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
