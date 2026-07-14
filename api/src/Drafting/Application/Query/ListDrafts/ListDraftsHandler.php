<?php

declare(strict_types=1);

namespace App\Drafting\Application\Query\ListDrafts;

use App\Drafting\Application\ReadModel\DraftSearch;
use App\Drafting\Application\ReadModel\DraftView;
use App\Shared\Application\Query\QueryHandler;

final class ListDraftsHandler implements QueryHandler
{
    public function __construct(private readonly DraftSearch $drafts)
    {
    }

    /** @return DraftView[] */
    public function __invoke(ListDrafts $query): array
    {
        return $this->drafts->forLead($query->leadId);
    }
}
