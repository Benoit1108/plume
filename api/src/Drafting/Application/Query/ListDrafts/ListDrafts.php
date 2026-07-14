<?php

declare(strict_types=1);

namespace App\Drafting\Application\Query\ListDrafts;

use App\Shared\Application\Query\Query;

final class ListDrafts implements Query
{
    public function __construct(public readonly string $leadId)
    {
    }
}
