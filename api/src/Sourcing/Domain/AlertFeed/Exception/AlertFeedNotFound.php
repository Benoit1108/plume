<?php

declare(strict_types=1);

namespace App\Sourcing\Domain\AlertFeed\Exception;

use App\Shared\Domain\Exception\NotFound;

final class AlertFeedNotFound extends NotFound
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Alert feed "%s" not found.', $id));
    }
}
