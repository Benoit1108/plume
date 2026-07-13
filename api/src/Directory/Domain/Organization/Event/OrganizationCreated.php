<?php

declare(strict_types=1);

namespace App\Directory\Domain\Organization\Event;

use App\Shared\Domain\DomainEvent;

final class OrganizationCreated implements DomainEvent
{
    public function __construct(
        public readonly string $organizationId,
        private readonly \DateTimeImmutable $occurredOn,
    ) {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
