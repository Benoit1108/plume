<?php

declare(strict_types=1);

namespace App\Prospecting\Application\ReadModel;

/** Progression de la semaine : objectif, actes faits (contacts + relances), série. */
final class WeeklyProgress
{
    public function __construct(
        public readonly int $target,
        public readonly int $done,
        public readonly int $streak,
    ) {
    }
}
