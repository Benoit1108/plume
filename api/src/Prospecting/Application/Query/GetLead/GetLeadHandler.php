<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Query\GetLead;

use App\Prospecting\Application\ReadModel\LeadSearch;
use App\Prospecting\Application\ReadModel\LeadView;
use App\Shared\Application\Query\QueryHandler;

final class GetLeadHandler implements QueryHandler
{
    public function __construct(private readonly LeadSearch $leads)
    {
    }

    public function __invoke(GetLead $query): LeadView
    {
        return $this->leads->get($query->id);
    }
}
