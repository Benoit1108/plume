<?php

declare(strict_types=1);

namespace App\Sourcing\Domain\CandidateLead;

/** État d'une candidate dans la file de tri. Immuable une fois hors de PENDING. */
enum CandidateStatus: string
{
    case PENDING = 'PENDING';     // en attente de tri
    case ACCEPTED = 'ACCEPTED';   // promue (nouvelle organisation + piste)
    case MERGED = 'MERGED';       // rattachée à une organisation existante + piste
    case REJECTED = 'REJECTED';   // écartée
}
