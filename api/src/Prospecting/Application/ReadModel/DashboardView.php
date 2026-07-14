<?php

declare(strict_types=1);

namespace App\Prospecting\Application\ReadModel;

/**
 * Le tableau de bord : la prospection paie-t-elle (taux), où en est le pipeline,
 * la régularité tient-elle (8 semaines vs objectif). Numérateurs ET dénominateurs
 * exposés — l'UI affiche « 4 / 12 », jamais un pourcentage sorti de nulle part.
 * Comptes par piste (lead_id distinct dans le journal), décision M1.5 n°2.
 */
final class DashboardView
{
    /**
     * @param PipelineSlice[] $pipeline       répartition par statut (ordre du kanban)
     * @param WeekActivity[]  $weeklyActivity 8 dernières semaines ISO, la plus ancienne d'abord
     * @param SegmentStats[]  $segments       segments ayant au moins une piste
     */
    public function __construct(
        public readonly int $contacted,
        public readonly int $replied,
        public readonly int $won,
        public readonly int $lost,
        public readonly int $activeLeads,
        public readonly int $outreachThisMonth,
        public readonly int $weeklyTarget,
        public readonly array $pipeline,
        public readonly array $weeklyActivity,
        public readonly array $segments,
    ) {
    }
}
