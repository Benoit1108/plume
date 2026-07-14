<?php

declare(strict_types=1);

namespace App\Drafting\Domain\Draft;

/** Cycle de vie d'un brouillon (génération asynchrone). */
enum DraftStatus: string
{
    case GENERATING = 'GENERATING';
    case READY = 'READY';
    case FAILED = 'FAILED';
}
