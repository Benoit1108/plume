<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Query\GetDashboard;

use App\Prospecting\Application\ReadModel\Dashboard;
use App\Prospecting\Application\ReadModel\DashboardView;
use App\Shared\Application\Query\QueryHandler;

final class GetDashboardHandler implements QueryHandler
{
    public function __construct(private readonly Dashboard $dashboard)
    {
    }

    public function __invoke(GetDashboard $query): DashboardView
    {
        return $this->dashboard->view();
    }
}
