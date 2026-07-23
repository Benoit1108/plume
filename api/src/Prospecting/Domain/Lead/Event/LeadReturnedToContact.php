<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead\Event;

use App\Shared\Domain\AbstractDomainEvent;

/** Correction : une piste marquée contactée par erreur est repassée à « À contacter ». */
final class LeadReturnedToContact extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $leadId,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
