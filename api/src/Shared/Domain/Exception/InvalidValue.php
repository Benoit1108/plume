<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/** Valeur refusée par un invariant (VO, guard d'agrégat) → HTTP 422. */
final class InvalidValue extends DomainError
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
