<?php

declare(strict_types=1);

namespace App\Directory\Domain\Organization\Exception;

use App\Shared\Domain\Exception\Conflict;
use App\Shared\Domain\ValueObject\EmailAddress;

final class DuplicateContactEmail extends Conflict
{
    public static function forEmail(EmailAddress $email): self
    {
        return new self(sprintf('A contact with email "%s" already exists in this organization.', $email->toString()));
    }
}
