<?php

declare(strict_types=1);

namespace App\Drafting\Application\Query\GetTemplate;

use App\Shared\Application\Query\Query;

final class GetTemplate implements Query
{
    public function __construct(public readonly string $id)
    {
    }
}
