<?php

declare(strict_types=1);

namespace App\Directory\Domain\Organization\Event;

use App\Shared\Domain\AbstractDomainEvent;

final class ContactAdded extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $organizationId,
        public readonly string $contactId,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
