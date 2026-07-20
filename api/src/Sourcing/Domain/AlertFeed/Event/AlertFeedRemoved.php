<?php

declare(strict_types=1);

namespace App\Sourcing\Domain\AlertFeed\Event;

use App\Shared\Domain\AbstractDomainEvent;

/** Un flux a été retiré de la configuration d'un tenant. */
final class AlertFeedRemoved extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $alertFeedId,
        public readonly string $tenantId,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
