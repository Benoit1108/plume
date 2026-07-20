<?php

declare(strict_types=1);

namespace App\Sourcing\Domain\AlertFeed\Exception;

use App\Shared\Domain\Exception\Conflict;

final class AlertFeedLimitReached extends Conflict
{
    public static function forTenant(int $max): self
    {
        return new self(sprintf('Alert feed limit reached (max %d).', $max));
    }
}
