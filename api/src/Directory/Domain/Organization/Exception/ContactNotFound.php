<?php

declare(strict_types=1);

namespace App\Directory\Domain\Organization\Exception;

use App\Directory\Domain\Organization\ContactId;
use App\Shared\Domain\Exception\NotFound;

final class ContactNotFound extends NotFound
{
    public static function withId(ContactId $id): self
    {
        return new self(sprintf('Contact "%s" not found in this organization.', $id->toString()));
    }
}
