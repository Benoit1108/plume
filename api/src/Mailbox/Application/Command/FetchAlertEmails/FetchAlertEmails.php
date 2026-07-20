<?php

declare(strict_types=1);

namespace App\Mailbox\Application\Command\FetchAlertEmails;

use App\Shared\Application\Command\Command;

/** Relève les emails d'alerte (label dédié) d'un tenant et les publie vers le Sourcing. */
final class FetchAlertEmails implements Command
{
    public function __construct(
        public readonly string $tenantId,
    ) {
    }
}
