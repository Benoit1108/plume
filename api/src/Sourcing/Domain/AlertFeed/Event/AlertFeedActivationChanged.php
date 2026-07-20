<?php

declare(strict_types=1);

namespace App\Sourcing\Domain\AlertFeed\Event;

use App\Shared\Domain\AbstractDomainEvent;

/** Un flux a été activé ou désactivé (seuls les flux actifs sont relevés). */
final class AlertFeedActivationChanged extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $alertFeedId,
        public readonly string $tenantId,
        public readonly bool $active,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
