<?php

declare(strict_types=1);

namespace App\Directory\Application\Query\OrganizationExists;

use App\Shared\Application\Query\Query;

/** Existe-t-il une organisation d'id donné dans ce tenant ? (langage publié cross-contexte) */
final class OrganizationExists implements Query
{
    public function __construct(
        public readonly string $organizationId,
        public readonly string $tenantId,
    ) {
    }
}
