<?php

declare(strict_types=1);

namespace App\Mailbox\Application;

/** Un connecteur OAuth par fournisseur (D1 : Gmail + Outlook) — factice sans identifiants. */
interface MailboxConnectorRegistry
{
    public function connectorFor(string $provider): MailboxConnector;
}
