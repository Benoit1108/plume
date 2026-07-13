<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead;

/** Origine de la piste — d'où vient ce démarchage. */
enum LeadSource: string
{
    case DIRECT = 'DIRECT';         // Démarchage direct (cœur du métier)
    case REFERRAL = 'REFERRAL';     // Recommandation / bouche-à-oreille
    case JOB_BOARD = 'JOB_BOARD';   // Annonce (ProZ, LinkedIn… — Sourcing M3)
    case OTHER = 'OTHER';
}
