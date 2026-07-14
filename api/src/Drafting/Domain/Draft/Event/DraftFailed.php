<?php

declare(strict_types=1);

namespace App\Drafting\Domain\Draft\Event;

use App\Shared\Domain\AbstractDomainEvent;

final class DraftFailed extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $draftId,
        public readonly string $reason,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
