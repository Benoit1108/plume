<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Query\GetDashboard;

use App\Prospecting\Application\ReadModel\DashboardPeriod;
use App\Shared\Application\Query\Query;

final class GetDashboard implements Query
{
    public function __construct(public readonly DashboardPeriod $period = DashboardPeriod::ALL)
    {
    }
}
