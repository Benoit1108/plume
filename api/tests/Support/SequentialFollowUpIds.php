<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Prospecting\Domain\Lead\FollowUpId;
use App\Prospecting\Domain\Lead\FollowUpIds;

/** Générateur d'identifiants de relance DÉTERMINISTE pour les tests (fu-1, fu-2, …). */
final class SequentialFollowUpIds implements FollowUpIds
{
    private int $counter = 0;

    public function next(): FollowUpId
    {
        return FollowUpId::fromString(sprintf('fu-%d', ++$this->counter));
    }
}
