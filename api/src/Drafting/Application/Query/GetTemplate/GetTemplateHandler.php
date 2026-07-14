<?php

declare(strict_types=1);

namespace App\Drafting\Application\Query\GetTemplate;

use App\Drafting\Application\ReadModel\TemplateSearch;
use App\Drafting\Application\ReadModel\TemplateView;
use App\Shared\Application\Query\QueryHandler;

final class GetTemplateHandler implements QueryHandler
{
    public function __construct(private readonly TemplateSearch $templates)
    {
    }

    public function __invoke(GetTemplate $query): TemplateView
    {
        return $this->templates->get($query->id);
    }
}
