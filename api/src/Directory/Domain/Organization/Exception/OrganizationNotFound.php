<?php

declare(strict_types=1);

namespace App\Directory\Domain\Organization\Exception;

use App\Directory\Domain\Organization\OrganizationId;
use App\Shared\Domain\Exception\NotFound;

final class OrganizationNotFound extends NotFound
{
    public static function withId(OrganizationId $id): self
    {
        return new self(sprintf('Organization "%s" not found.', $id->toString()));
    }
}
