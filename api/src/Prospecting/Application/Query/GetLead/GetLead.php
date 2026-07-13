<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Query\GetLead;

use App\Shared\Application\Query\Query;

final class GetLead implements Query
{
    public function __construct(public readonly string $id)
    {
    }
}
