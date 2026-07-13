<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead\Exception;

use App\Prospecting\Domain\Lead\PipelineStatus;
use App\Shared\Domain\Exception\Conflict;

/** Pas de relance planifiable sur une piste terminale ou en pause. */
final class FollowUpNotAllowed extends Conflict
{
    public static function inStatus(PipelineStatus $status): self
    {
        return new self(sprintf('No follow-up can be scheduled while the lead is %s.', $status->value));
    }
}
