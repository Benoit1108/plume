<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Command\IngestCandidate;

use App\Shared\Application\Command\Command;

/** Fait entrer une annonce dans la file de tri (parser, source factice, saisie). */
final class IngestCandidate implements Command
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $source,
        public readonly string $title,
        public readonly ?string $organizationName = null,
        public readonly ?string $languagePair = null,
        public readonly ?string $url = null,
        public readonly ?string $excerpt = null,
        public readonly ?string $externalId = null,
        public readonly ?string $postedAt = null,
    ) {
    }
}
