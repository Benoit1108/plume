<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Query\ListLeads;

use App\Prospecting\Application\ReadModel\LeadPage;
use App\Prospecting\Application\ReadModel\LeadSearch;
use App\Shared\Application\Query\QueryHandler;

final class ListLeadsHandler implements QueryHandler
{
    public function __construct(private readonly LeadSearch $leads)
    {
    }

    public function __invoke(ListLeads $query): LeadPage
    {
        $page = max(1, $query->page);
        $itemsPerPage = min(200, max(1, $query->itemsPerPage));

        return $this->leads->search($query->status, $query->priority, $query->segment, $page, $itemsPerPage);
    }
}
