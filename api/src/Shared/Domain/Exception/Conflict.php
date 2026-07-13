<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/** Conflit avec l'état existant (doublon, transition interdite) → HTTP 409. */
abstract class Conflict extends DomainError
{
}
