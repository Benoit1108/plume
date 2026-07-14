<?php

declare(strict_types=1);

namespace App\Drafting\Domain\Draft\Event;

use App\Shared\Domain\AbstractDomainEvent;

final class DraftDeleted extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $draftId,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
