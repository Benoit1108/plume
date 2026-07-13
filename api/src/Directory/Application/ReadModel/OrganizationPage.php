<?php

declare(strict_types=1);

namespace App\Directory\Application\ReadModel;

/** Page de résultats (pagination portée par le read model). */
final class OrganizationPage
{
    /** @param OrganizationView[] $items */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $itemsPerPage,
    ) {
    }
}
