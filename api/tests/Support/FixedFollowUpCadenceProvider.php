<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Prospecting\Application\FollowUpCadenceProvider;
use App\Prospecting\Domain\Lead\FollowUpCadence;

/** Provider de test : rend une cadence fixée (défaut [7,21,45] si non précisée). */
final class FixedFollowUpCadenceProvider implements FollowUpCadenceProvider
{
    private readonly FollowUpCadence $cadence;

    public function __construct(?FollowUpCadence $cadence = null)
    {
        $this->cadence = $cadence ?? FollowUpCadence::default();
    }

    public function forCurrentTenant(): FollowUpCadence
    {
        return $this->cadence;
    }
}
