<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Fetcher;

use App\Mailbox\Application\AlertEmailFetcher;
use App\Mailbox\Application\AlertEmailFetcherRegistry;

/**
 * Route vers la relève d'alertes du fournisseur (factice sans identifiants OAuth) : Gmail réel
 * dès que `GOOGLE_CLIENT_ID` est présent, Outlook réel dès que `MICROSOFT_CLIENT_ID` l'est —
 * même politique que les registries d'envoi et de relève de réponses.
 */
final class ProviderAlertEmailFetcherRegistry implements AlertEmailFetcherRegistry
{
    public function __construct(
        private readonly FakeAlertEmailFetcher $fake,
        private readonly GmailAlertEmailFetcher $gmail,
        private readonly OutlookAlertEmailFetcher $outlook,
        private readonly string $googleClientId,
        private readonly string $microsoftClientId,
    ) {
    }

    public function fetcherFor(string $provider): AlertEmailFetcher
    {
        return match ($provider) {
            'GMAIL' => '' === trim($this->googleClientId) ? $this->fake : $this->gmail,
            'OUTLOOK' => '' === trim($this->microsoftClientId) ? $this->fake : $this->outlook,
            default => $this->fake,
        };
    }
}
