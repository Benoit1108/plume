<?php

declare(strict_types=1);

namespace App\Mailbox\Application\Command\ConnectMailbox;

use App\Shared\Application\Command\Command;

/** Connecte (ou reconnecte) LA boîte du tenant à partir d'un code OAuth validé. */
final class ConnectMailbox implements Command
{
    public function __construct(
        public readonly string $mailboxId,
        public readonly string $tenantId,
        public readonly string $provider,
        public readonly string $code,
    ) {
    }
}
