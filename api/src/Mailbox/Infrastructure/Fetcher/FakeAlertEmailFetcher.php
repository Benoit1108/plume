<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Fetcher;

use App\Mailbox\Application\AlertEmailFetcher;
use App\Mailbox\Application\FetchedAlertEmail;

/**
 * Relève d'alertes factice (défaut sans identifiants OAuth) : joue la boucle de bout en bout
 * sans réseau (dev / CI / E2E). `externalId` FIXE → ingestion idempotente. L'adaptateur réel
 * Gmail (`GmailAlertEmailFetcher`) prend le relais dès que `GOOGLE_CLIENT_ID` est présent ;
 * l'adaptateur Outlook réel reste un suivi.
 */
final class FakeAlertEmailFetcher implements AlertEmailFetcher
{
    public function fetch(string $refreshTokenPlain, string $label): array
    {
        return [
            new FetchedAlertEmail(
                'jobs-noreply@linkedin.com',
                'Traducteur EN>FR — poste chez Studio Démo',
                "Bonjour,\n\nUne offre correspond à votre recherche : traduction/sous-titrage EN>FR.\nVoir l'offre : https://example.test/linkedin/job/demo-1\n\nLinkedIn",
                'demo-email-linkedin-1',
            ),
        ];
    }
}
