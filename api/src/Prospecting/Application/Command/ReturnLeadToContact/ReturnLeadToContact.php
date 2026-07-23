<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Command\ReturnLeadToContact;

use App\Shared\Application\Command\Command;

/** Repasser une piste (contactée par erreur) à « À contacter ». */
final class ReturnLeadToContact implements Command
{
    public function __construct(
        public readonly string $leadId,
        /** Fourni par le worker (chemin async) : vérifié au chargement. HTTP : null, le SQLFilter isole. */
        public readonly ?string $tenantId = null,
    ) {
    }
}
