<?php

declare(strict_types=1);

namespace App\Directory\Application\Query\ListOrganizations;

use App\Shared\Application\Query\Query;

final class ListOrganizations implements Query
{
    public function __construct(
        public readonly ?string $type = null,
        public readonly ?string $search = null,
    ) {
    }
}
