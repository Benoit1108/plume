<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead\Exception;

use App\Shared\Domain\Exception\Conflict;

/** L'organisation est marquée « ne pas contacter » (RGPD) : démarchage interdit. */
final class OrganizationNotContactable extends Conflict
{
    public static function forOrganization(string $organizationId): self
    {
        return new self(sprintf('Organization "%s" is marked do-not-contact.', $organizationId));
    }
}
