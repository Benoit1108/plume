<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Query\ListLeads;

use App\Shared\Application\Query\Query;

final class ListLeads implements Query
{
    public function __construct(
        public readonly ?string $status = null,
        public readonly ?string $priority = null,
        public readonly ?string $segment = null,
        public readonly int $page = 1,
        public readonly int $itemsPerPage = 100,
    ) {
    }
}
