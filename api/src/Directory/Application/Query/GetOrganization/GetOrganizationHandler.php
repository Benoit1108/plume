<?php

declare(strict_types=1);

namespace App\Directory\Application\Query\GetOrganization;

use App\Directory\Application\ReadModel\OrganizationSearch;
use App\Directory\Application\ReadModel\OrganizationView;
use App\Shared\Application\Query\QueryHandler;

final class GetOrganizationHandler implements QueryHandler
{
    public function __construct(private readonly OrganizationSearch $organizations)
    {
    }

    public function __invoke(GetOrganization $query): OrganizationView
    {
        return $this->organizations->get($query->id);
    }
}
