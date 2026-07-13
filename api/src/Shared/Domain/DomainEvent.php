<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Marqueur des domain events. PHP pur : aucune dépendance framework.
 */
interface DomainEvent
{
    /** Identifiant unique de l'event (idempotence des projections). */
    public function eventId(): string;

    public function occurredOn(): \DateTimeImmutable;
}
