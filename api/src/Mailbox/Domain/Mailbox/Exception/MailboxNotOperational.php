<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Mailbox\Exception;

use App\Mailbox\Domain\Mailbox\MailboxStatus;
use App\Shared\Domain\Exception\Conflict;

/** Opération (envoi, relève) sur une boîte révoquée ou en erreur. */
final class MailboxNotOperational extends Conflict
{
    public static function inStatus(MailboxStatus $status): self
    {
        return new self(sprintf('Mailbox is not operational (status "%s").', $status->value));
    }
}
