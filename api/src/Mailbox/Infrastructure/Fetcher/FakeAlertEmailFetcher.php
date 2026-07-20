<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Fetcher;

use App\Mailbox\Application\AlertEmailFetcher;
use App\Mailbox\Application\FetchedAlertEmail;

/**
 * Relève d'alertes factice (défaut sans adaptateur réel) : joue la boucle de bout en bout
 * sans réseau (dev / CI / E2E). `externalId` FIXE → ingestion idempotente. Les adaptateurs
 * réels Gmail/Outlook (lecture du label) sont un suivi de M3.2.
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
