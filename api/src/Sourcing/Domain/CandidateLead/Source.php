<?php

declare(strict_types=1);

namespace App\Sourcing\Domain\CandidateLead;

/** Provenance d'une annonce ingérée (Sourcing M3). */
enum Source: string
{
    case PROZ = 'PROZ';
    case LINKEDIN = 'LINKEDIN';
    case TRANSLATORSCAFE = 'TRANSLATORSCAFE';
    case RSS = 'RSS';
    case MANUAL = 'MANUAL';         // saisie/collage manuel (amorçage M3.0)

    /** Provenance fine portée par la Piste à la promotion (mappée sur `LeadSource`). */
    public function toLeadSource(): string
    {
        return match ($this) {
            self::PROZ => 'PROZ',
            self::LINKEDIN => 'LINKEDIN',
            self::TRANSLATORSCAFE => 'TRANSLATORSCAFE',
            self::RSS => 'RSS',
            self::MANUAL => 'JOB_BOARD',
        };
    }
}
