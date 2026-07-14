<?php

declare(strict_types=1);

namespace App\Mailbox\Application\Exception;

/** L'échange OAuth n'a pas abouti (code expiré, refus, panne provider). */
final class MailboxConnectionFailed extends \RuntimeException
{
    public static function because(string $reason, ?\Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
