<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Marqueur des domain events. PHP pur : aucune dépendance framework.
 */
interface DomainEvent
{
    /**
     * Assigne l'identifiant unique de l'event. Appelé UNE fois par l'outbox (EventBus) à la
     * publication : le domaine ne génère aucun identifiant (pureté / déterminisme des tests).
     */
    public function assignId(string $eventId): void;

    /** Identifiant unique de l'event (idempotence des projections). Assigné à la publication. */
    public function eventId(): string;

    public function occurredOn(): \DateTimeImmutable;
}
