<?php

declare(strict_types=1);

namespace App\Drafting\Application\Query\GetDraft;

use App\Drafting\Application\ReadModel\DraftSearch;
use App\Drafting\Application\ReadModel\DraftView;
use App\Shared\Application\Query\QueryHandler;

final class GetDraftHandler implements QueryHandler
{
    public function __construct(private readonly DraftSearch $drafts)
    {
    }

    public function __invoke(GetDraft $query): DraftView
    {
        return $this->drafts->get($query->id);
    }
}
