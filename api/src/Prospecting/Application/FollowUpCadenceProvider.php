<?php

declare(strict_types=1);

namespace App\Prospecting\Application;

use App\Prospecting\Domain\Lead\FollowUpCadence;

/**
 * Fournit la SÉQUENCE de relance du tenant courant (configurée par la traductrice, défaut [7,21,45]).
 * Port : le domaine reçoit une cadence, il ne va pas la chercher lui-même.
 */
interface FollowUpCadenceProvider
{
    public function forCurrentTenant(): FollowUpCadence;
}
