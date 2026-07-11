<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Marqueur des domain events. PHP pur : aucune dépendance framework.
 */
interface DomainEvent
{
    public function occurredOn(): \DateTimeImmutable;
}
