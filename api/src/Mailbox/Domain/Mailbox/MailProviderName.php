<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Mailbox;

/** Fournisseur de boîte email (D1 : Gmail d'abord, Outlook en M2.4). */
enum MailProviderName: string
{
    case GMAIL = 'GMAIL';
    case OUTLOOK = 'OUTLOOK';
}
