<?php

declare(strict_types=1);

namespace App\Prospecting\Application\ReadModel;

/** Page de résultats (pagination portée par le read model). */
final class LeadPage
{
    /** @param LeadView[] $items */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $itemsPerPage,
    ) {
    }
}
