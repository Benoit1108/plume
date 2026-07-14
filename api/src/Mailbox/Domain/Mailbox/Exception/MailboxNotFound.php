<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Mailbox\Exception;

use App\Shared\Domain\Exception\NotFound;

final class MailboxNotFound extends NotFound
{
    public static function forTenant(): self
    {
        return new self('No connected mailbox.');
    }
}
