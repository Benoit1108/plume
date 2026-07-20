<?php

declare(strict_types=1);

namespace App\Sourcing\Application\ReadModel;

/** Un flux d'annonces configuré (lecture). */
final class AlertFeedRow
{
    public function __construct(
        public readonly string $id,
        public readonly string $source,
        public readonly string $url,
        public readonly string $label,
        public readonly bool $active,
        public readonly string $createdAt,
    ) {
    }
}
