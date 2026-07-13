<?php

declare(strict_types=1);

namespace App\Directory\Domain\Organization\Event;

use App\Shared\Domain\AbstractDomainEvent;

final class OrganizationDoNotContactCleared extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $organizationId,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
