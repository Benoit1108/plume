<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead\Exception;

use App\Prospecting\Domain\Lead\LeadId;
use App\Shared\Domain\Exception\NotFound;

final class LeadNotFound extends NotFound
{
    public static function withId(LeadId $id): self
    {
        return new self(sprintf('Lead "%s" not found.', $id->toString()));
    }
}
