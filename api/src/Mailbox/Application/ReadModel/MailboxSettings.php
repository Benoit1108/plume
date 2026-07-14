<?php

declare(strict_types=1);

namespace App\Mailbox\Application\ReadModel;

/** Port de lecture de la boîte du tenant courant (fail-closed tenant). */
interface MailboxSettings
{
    public function current(): ?MailboxView;
}
