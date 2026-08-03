<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Socle des domain events : identifiant unique (idempotence des consommateurs —
 * un retry Messenger ne rejoue jamais deux fois la même projection) + horodatage.
 * Les events concrets sont « riches » : ils portent le tenant et les données
 * nécessaires aux projections, sans recharger d'agrégat.
 *
 * L'identifiant n'est PAS généré par le domaine : il est assigné UNE fois par l'outbox
 * (`MessengerEventBus`) à la publication, via le port `IdGenerator` (UUID v7, ordonné et cohérent
 * avec les clés primaires). Le domaine reste pur/déterministe (aucun `random_bytes`).
 */
abstract class AbstractDomainEvent implements DomainEvent
{
    private ?string $eventId = null;

    public function __construct(private readonly \DateTimeImmutable $occurredOn)
    {
    }

    final public function assignId(string $eventId): void
    {
        if (null !== $this->eventId) {
            throw new \LogicException('Event id already assigned.');
        }
        $this->eventId = $eventId;
    }

    final public function eventId(): string
    {
        return $this->eventId
            ?? throw new \LogicException('Event id not assigned — publish the event through the EventBus first.');
    }

    final public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
