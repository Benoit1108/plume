<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Query\GetLeadTimeline;

use App\Shared\Application\Query\Query;

final class GetLeadTimeline implements Query
{
    public function __construct(public readonly string $leadId)
    {
    }
}
