<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead;

/** Pourquoi une relance en attente a été annulée (traçabilité du journal). */
enum FollowUpCancelReason: string
{
    case REPLY = 'REPLY';         // réponse reçue : la discussion est ouverte
    case TERMINAL = 'TERMINAL';   // piste gagnée/perdue
    case PAUSED = 'PAUSED';       // piste mise en pause
    case MANUAL = 'MANUAL';       // annulation volontaire
}
