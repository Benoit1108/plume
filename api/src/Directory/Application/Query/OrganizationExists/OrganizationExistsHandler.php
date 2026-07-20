<?php

declare(strict_types=1);

namespace App\Directory\Application\Query\OrganizationExists;

use App\Directory\Application\ReadModel\OrganizationSearch;
use App\Shared\Application\Query\QueryHandler;

final class OrganizationExistsHandler implements QueryHandler
{
    public function __construct(private readonly OrganizationSearch $organizations)
    {
    }

    public function __invoke(OrganizationExists $query): bool
    {
        return $this->organizations->existsById($query->organizationId, $query->tenantId);
    }
}
