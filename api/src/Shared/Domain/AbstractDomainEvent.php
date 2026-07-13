<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use App\Shared\Domain\Uid\UuidV4;

/**
 * Socle des domain events : identifiant unique (idempotence des consommateurs —
 * un retry Messenger ne rejoue jamais deux fois la même projection) + horodatage.
 * Les events concrets sont « riches » : ils portent le tenant et les données
 * nécessaires aux projections, sans recharger d'agrégat.
 */
abstract class AbstractDomainEvent implements DomainEvent
{
    private readonly string $eventId;

    public function __construct(private readonly \DateTimeImmutable $occurredOn)
    {
        $this->eventId = UuidV4::generate();
    }

    final public function eventId(): string
    {
        return $this->eventId;
    }

    final public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
