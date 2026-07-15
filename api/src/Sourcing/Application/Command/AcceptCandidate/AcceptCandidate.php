<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Command\AcceptCandidate;

use App\Shared\Application\Command\Command;

/** Accepter une candidate : crée une NOUVELLE organisation + une piste. */
final class AcceptCandidate implements Command
{
    public function __construct(
        public readonly string $candidateLeadId,
        public readonly string $organizationName,
        public readonly string $organizationType,
        public readonly string $languagePair,
        public readonly string $segment,
        public readonly string $priority,
        public readonly ?string $website = null,
    ) {
    }
}
