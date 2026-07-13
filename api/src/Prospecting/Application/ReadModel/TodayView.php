<?php

declare(strict_types=1);

namespace App\Prospecting\Application\ReadModel;

/** L'écran « Aujourd'hui » : quoi faire maintenant + où on en est cette semaine. */
final class TodayView
{
    /**
     * @param LeadView[] $followUpsDue relances dues (retards en premier)
     * @param LeadView[] $toContact    pistes à contacter (priorité puis ancienneté)
     */
    public function __construct(
        public readonly array $followUpsDue,
        public readonly array $toContact,
        public readonly WeeklyProgress $weeklyProgress,
    ) {
    }
}
