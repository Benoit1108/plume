<?php

declare(strict_types=1);

namespace App\Sourcing\Domain\AlertFeed\Event;

use App\Shared\Domain\AbstractDomainEvent;

/** Un flux d'annonces a été ajouté à la configuration d'un tenant. */
final class AlertFeedAdded extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $alertFeedId,
        public readonly string $tenantId,
        public readonly string $source,
        public readonly string $url,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
