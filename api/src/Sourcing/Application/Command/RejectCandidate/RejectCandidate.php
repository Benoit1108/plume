<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Command\RejectCandidate;

use App\Shared\Application\Command\Command;

/** Écarter une candidate (son empreinte reste — anti-réapparition, ADR-0021). */
final class RejectCandidate implements Command
{
    public function __construct(
        public readonly string $candidateLeadId,
    ) {
    }
}
