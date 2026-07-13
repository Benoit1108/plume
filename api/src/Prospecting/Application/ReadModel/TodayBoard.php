<?php

declare(strict_types=1);

namespace App\Prospecting\Application\ReadModel;

/** Port de lecture de l'écran « Aujourd'hui » (fail-closed tenant). */
interface TodayBoard
{
    public function view(): TodayView;
}
