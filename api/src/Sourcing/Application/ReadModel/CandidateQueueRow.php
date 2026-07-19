<?php

declare(strict_types=1);

namespace App\Sourcing\Application\ReadModel;

/** Une ligne de la file de tri (annonce en attente). */
final class CandidateQueueRow
{
    public function __construct(
        public readonly string $id,
        public readonly string $source,
        public readonly string $status,
        public readonly string $title,
        public readonly ?string $organizationName,
        public readonly ?string $languagePair,
        public readonly ?string $url,
        public readonly ?string $excerpt,
        public readonly ?string $postedAt,
        public readonly string $ingestedAt,
    ) {
    }
}
