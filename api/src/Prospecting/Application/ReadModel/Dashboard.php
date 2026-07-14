<?php

declare(strict_types=1);

namespace App\Prospecting\Application\ReadModel;

/** Port de lecture du tableau de bord (fail-closed tenant). */
interface Dashboard
{
    public function view(): DashboardView;
}
