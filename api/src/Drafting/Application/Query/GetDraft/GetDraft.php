<?php

declare(strict_types=1);

namespace App\Drafting\Application\Query\GetDraft;

use App\Shared\Application\Query\Query;

final class GetDraft implements Query
{
    public function __construct(public readonly string $id)
    {
    }
}
