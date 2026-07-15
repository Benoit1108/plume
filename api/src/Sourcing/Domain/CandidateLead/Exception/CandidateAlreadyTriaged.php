<?php

declare(strict_types=1);

namespace App\Sourcing\Domain\CandidateLead\Exception;

use App\Shared\Domain\Exception\Conflict;
use App\Sourcing\Domain\CandidateLead\CandidateStatus;

/** Re-trier une candidate déjà triée (double-clic, redélivrance) → 409. */
final class CandidateAlreadyTriaged extends Conflict
{
    public static function is(CandidateStatus $status): self
    {
        return new self(sprintf('Candidate already triaged (%s).', $status->value));
    }
}
