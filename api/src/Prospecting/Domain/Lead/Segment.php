<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead;

/** Segment métier ciblé (conditionne le ton de la génération). */
enum Segment: string
{
    case PUBLISHING = 'PUBLISHING';     // Édition / livres
    case AUDIOVISUAL = 'AUDIOVISUAL';   // Audiovisuel / sous-titrage
    case TECHNICAL = 'TECHNICAL';       // Technique / agences
    case OTHER = 'OTHER';
}
