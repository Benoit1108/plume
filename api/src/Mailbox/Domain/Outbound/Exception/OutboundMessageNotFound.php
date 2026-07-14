<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Outbound\Exception;

use App\Shared\Domain\Exception\NotFound;

final class OutboundMessageNotFound extends NotFound
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Outbound message "%s" not found.', $id));
    }
}
