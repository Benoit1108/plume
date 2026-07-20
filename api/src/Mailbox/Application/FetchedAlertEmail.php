<?php

declare(strict_types=1);

namespace App\Mailbox\Application;

/** Un email lu sous le label d'alertes (contenu brut minimal pour le Sourcing). */
final class FetchedAlertEmail
{
    public function __construct(
        public readonly string $fromAddress,
        public readonly string $subject,
        public readonly string $body,
        public readonly string $externalId,
    ) {
    }
}
