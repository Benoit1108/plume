<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\OAuth;

use App\Mailbox\Application\MailboxConnector;
use App\Mailbox\Application\MailboxConnectorRegistry;

/**
 * Route vers le connecteur du fournisseur. Sans identifiants pour ce
 * fournisseur → connecteur factice (dev/CI/E2E sans compte réel, tout provider).
 */
final class ProviderMailboxConnectorRegistry implements MailboxConnectorRegistry
{
    public function __construct(
        private readonly FakeMailboxConnector $fake,
        private readonly GmailConnector $gmail,
        private readonly OutlookConnector $outlook,
        private readonly string $googleClientId,
        private readonly string $microsoftClientId,
    ) {
    }

    public function connectorFor(string $provider): MailboxConnector
    {
        return match ($provider) {
            'GMAIL' => '' === trim($this->googleClientId) ? $this->fake : $this->gmail,
            'OUTLOOK' => '' === trim($this->microsoftClientId) ? $this->fake : $this->outlook,
            default => $this->fake,
        };
    }
}
