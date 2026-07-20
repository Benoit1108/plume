<?php

declare(strict_types=1);

namespace App\Mailbox\Application;

/**
 * Port de relève des alertes : lit UNIQUEMENT les emails sous le label dédié (minimisation,
 * ADR-0017 amendé) — jamais toute la boîte.
 */
interface AlertEmailFetcher
{
    /** @return FetchedAlertEmail[] */
    public function fetch(string $refreshTokenPlain, string $label): array;
}
