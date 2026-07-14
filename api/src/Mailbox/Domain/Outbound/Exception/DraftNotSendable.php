<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Outbound\Exception;

use App\Shared\Domain\Exception\Conflict;

/** Seul un brouillon relu (READY) part — draft-first, jusqu'au bout. */
final class DraftNotSendable extends Conflict
{
    public static function inStatus(string $status): self
    {
        return new self(sprintf('Only a reviewed (READY) draft can be sent (status "%s").', $status));
    }
}
