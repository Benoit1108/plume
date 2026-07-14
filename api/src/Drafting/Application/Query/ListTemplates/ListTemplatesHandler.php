<?php

declare(strict_types=1);

namespace App\Drafting\Application\Query\ListTemplates;

use App\Drafting\Application\ReadModel\TemplateSearch;
use App\Drafting\Application\ReadModel\TemplateView;
use App\Shared\Application\Query\QueryHandler;

final class ListTemplatesHandler implements QueryHandler
{
    public function __construct(private readonly TemplateSearch $templates)
    {
    }

    /** @return TemplateView[] */
    public function __invoke(ListTemplates $query): array
    {
        return $this->templates->all();
    }
}
