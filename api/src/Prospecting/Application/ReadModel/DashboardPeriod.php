<?php

declare(strict_types=1);

namespace App\Prospecting\Application\ReadModel;

/**
 * Fenêtre temporelle du tableau de bord. Elle ne s'applique qu'aux métriques tirées du JOURNAL
 * (taux contactées/réponses/gagnées/perdues, segments, délai de 1re réponse) — pas aux instantanés
 * d'état (pistes actives, pipeline, valeur estimée), qui décrivent la situation ACTUELLE quelle que
 * soit la période. Fenêtres GLISSANTES (N derniers jours/mois depuis maintenant), non ambiguës.
 */
enum DashboardPeriod: string
{
    case ALL = 'all';
    case LAST_30_DAYS = '30d';
    case LAST_90_DAYS = '90d';
    case LAST_12_MONTHS = '12m';

    /** Tolérant : une valeur inconnue/absente retombe sur « depuis le début » (jamais d'erreur). */
    public static function fromString(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::ALL;
    }

    /** Borne basse (incluse) de la fenêtre, ou null pour « depuis le début ». */
    public function since(\DateTimeImmutable $now): ?\DateTimeImmutable
    {
        return match ($this) {
            self::ALL => null,
            self::LAST_30_DAYS => $now->modify('-30 days'),
            self::LAST_90_DAYS => $now->modify('-90 days'),
            self::LAST_12_MONTHS => $now->modify('-12 months'),
        };
    }
}
