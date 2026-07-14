<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Mailbox;

enum MailboxStatus: string
{
    case CONNECTED = 'CONNECTED';   // opérationnelle
    case ERROR = 'ERROR';           // refresh/sync en échec — reconnexion nécessaire
    case REVOKED = 'REVOKED';       // déconnectée volontairement (tokens effacés)
}
