<?php

declare(strict_types=1);

namespace App\Directory\Application\Query\ListOrganizations;

use App\Directory\Application\ReadModel\OrganizationPage;
use App\Directory\Application\ReadModel\OrganizationSearch;
use App\Shared\Application\Query\QueryHandler;

final class ListOrganizationsHandler implements QueryHandler
{
    public function __construct(private readonly OrganizationSearch $organizations)
    {
    }

    public function __invoke(ListOrganizations $query): OrganizationPage
    {
        $page = max(1, $query->page);
        $itemsPerPage = min(100, max(1, $query->itemsPerPage));

        return $this->organizations->search($query->type, $query->search, $page, $itemsPerPage);
    }
}
