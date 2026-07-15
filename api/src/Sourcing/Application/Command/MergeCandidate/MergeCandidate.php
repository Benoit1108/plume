<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Command\MergeCandidate;

use App\Shared\Application\Command\Command;

/** Fusionner une candidate : rattache à une organisation EXISTANTE + crée une piste. */
final class MergeCandidate implements Command
{
    public function __construct(
        public readonly string $candidateLeadId,
        public readonly string $organizationId,
        public readonly string $languagePair,
        public readonly string $segment,
        public readonly string $priority,
    ) {
    }
}
