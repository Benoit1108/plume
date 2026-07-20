<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Query\FindActiveLead;

use App\Prospecting\Application\ReadModel\LeadSearch;
use App\Shared\Application\Query\QueryHandler;

final class FindActiveLeadHandler implements QueryHandler
{
    public function __construct(private readonly LeadSearch $leads)
    {
    }

    public function __invoke(FindActiveLead $query): ?string
    {
        return $this->leads->activeLeadIdForOrganization($query->tenantId, $query->organizationId);
    }
}
