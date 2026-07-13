<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/**
 * Segment métier — partagé entre le Répertoire (domaines d'une Organisation)
 * et la Prospection (segment d'une Piste). Conditionne le ton de la génération.
 */
enum Segment: string
{
    case PUBLISHING = 'PUBLISHING';     // Édition / livres
    case AUDIOVISUAL = 'AUDIOVISUAL';   // Audiovisuel / sous-titrage
    case TECHNICAL = 'TECHNICAL';       // Technique / agences
    case OTHER = 'OTHER';
}
