<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead;

/** Origine de la piste — d'où vient ce démarchage. */
enum LeadSource: string
{
    case DIRECT = 'DIRECT';         // Démarchage direct (cœur du métier)
    case REFERRAL = 'REFERRAL';     // Recommandation / bouche-à-oreille
    case JOB_BOARD = 'JOB_BOARD';   // Annonce générique (source non précisée)
    // Provenances fines issues du Sourcing (M3, ADR-0020) :
    case PROZ = 'PROZ';
    case LINKEDIN = 'LINKEDIN';
    case TRANSLATORSCAFE = 'TRANSLATORSCAFE';
    case RSS = 'RSS';
    case OTHER = 'OTHER';
}
