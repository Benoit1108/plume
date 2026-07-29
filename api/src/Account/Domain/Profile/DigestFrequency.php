<?php

declare(strict_types=1);

namespace App\Account\Domain\Profile;

/**
 * Fréquence du digest email des notifications (récap des notifications non lues de la période).
 * NONE = pas de digest (in-app seulement). Défaut : DAILY (nudge léger, cohérent avec l'objectif
 * de prospection régulière) — l'utilisatrice peut passer à WEEKLY ou couper.
 */
enum DigestFrequency: string
{
    case NONE = 'NONE';
    case DAILY = 'DAILY';
    case WEEKLY = 'WEEKLY';
}
