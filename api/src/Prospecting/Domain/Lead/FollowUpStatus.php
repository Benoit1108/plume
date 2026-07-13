<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead;

/** État d'une relance : une seule PENDING à la fois par piste (décision M1.3 n°2). */
enum FollowUpStatus: string
{
    case PENDING = 'PENDING';
    case DONE = 'DONE';
    case CANCELLED = 'CANCELLED';
}
