<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead\Exception;

use App\Shared\Domain\Exception\Conflict;

/** Une seule piste active par organisation (décision M1.2 n°1). */
final class ActiveLeadAlreadyExists extends Conflict
{
    public static function forOrganization(string $organizationId): self
    {
        return new self(sprintf('An active lead already exists for organization "%s".', $organizationId));
    }
}
