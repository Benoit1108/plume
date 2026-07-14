<?php

declare(strict_types=1);

namespace App\Prospecting\Application\ReadModel;

/** Actes de démarchage d'une semaine ISO (weekStart = lundi local, Y-m-d). */
final class WeekActivity
{
    public function __construct(
        public readonly string $weekStart,
        public readonly int $acts,
    ) {
    }
}
