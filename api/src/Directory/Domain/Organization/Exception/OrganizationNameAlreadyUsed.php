<?php

declare(strict_types=1);

namespace App\Directory\Domain\Organization\Exception;

use App\Shared\Domain\Exception\Conflict;

/** Le nom d'organisation est unique par tenant (insensible à la casse). */
final class OrganizationNameAlreadyUsed extends Conflict
{
    public static function named(string $name): self
    {
        return new self(sprintf('An organization named "%s" already exists.', $name));
    }
}
