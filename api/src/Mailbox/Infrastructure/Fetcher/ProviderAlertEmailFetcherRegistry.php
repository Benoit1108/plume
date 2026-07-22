<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Fetcher;

use App\Mailbox\Application\AlertEmailFetcher;
use App\Mailbox\Application\AlertEmailFetcherRegistry;

/**
 * Route vers la relève d'alertes du fournisseur (factice sans identifiants OAuth). Gmail est
 * réel dès que `GOOGLE_CLIENT_ID` est présent ; Outlook reste factice tant qu'aucun
 * `OutlookAlertEmailFetcher` n'est livré (suivi — le compte de test est Gmail).
 */
final class ProviderAlertEmailFetcherRegistry implements AlertEmailFetcherRegistry
{
    public function __construct(
        private readonly FakeAlertEmailFetcher $fake,
        private readonly GmailAlertEmailFetcher $gmail,
        private readonly string $googleClientId,
    ) {
    }

    public function fetcherFor(string $provider): AlertEmailFetcher
    {
        return match ($provider) {
            'GMAIL' => '' === trim($this->googleClientId) ? $this->fake : $this->gmail,
            default => $this->fake,
        };
    }
}
