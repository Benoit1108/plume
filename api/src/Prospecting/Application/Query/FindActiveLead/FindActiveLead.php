<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Query\FindActiveLead;

use App\Shared\Application\Query\Query;

/** Id de la piste active d'une organisation (ou null). Langage publié cross-contexte. */
final class FindActiveLead implements Query
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $organizationId,
    ) {
    }
}
