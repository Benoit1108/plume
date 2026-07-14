<?php

declare(strict_types=1);

namespace App\Drafting\Domain\Draft;

/** Type de message généré (conditionne le ton et la structure du prompt). */
enum DraftType: string
{
    case APPLICATION_EMAIL = 'APPLICATION_EMAIL';   // Mail de candidature spontanée
    case COVER_LETTER = 'COVER_LETTER';             // Lettre de motivation
    case FOLLOW_UP_EMAIL = 'FOLLOW_UP_EMAIL';       // Mail de relance
}
