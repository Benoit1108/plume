<?php

declare(strict_types=1);

namespace App\Sourcing\Domain\CandidateLead\Exception;

use App\Shared\Domain\Exception\NotFound;

final class CandidateLeadNotFound extends NotFound
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Candidate lead "%s" not found.', $id));
    }
}
