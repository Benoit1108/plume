<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead\Exception;

use App\Prospecting\Domain\Lead\PipelineStatus;
use App\Shared\Domain\Exception\Conflict;

final class IllegalStatusTransition extends Conflict
{
    public static function between(PipelineStatus $from, PipelineStatus $to): self
    {
        return new self(sprintf('Transition de statut interdite : %s → %s.', $from->value, $to->value));
    }
}
