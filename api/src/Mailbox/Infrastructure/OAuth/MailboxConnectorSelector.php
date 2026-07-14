<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\OAuth;

use App\Mailbox\Application\MailboxConnector;
use App\Mailbox\Application\OAuthTokens;

/**
 * Sélection par l'env (pattern MessageGeneratorSelector) :
 * GOOGLE_CLIENT_ID présent → Gmail réel, absent → factice (coût zéro).
 */
final class MailboxConnectorSelector implements MailboxConnector
{
    public function __construct(
        private readonly FakeMailboxConnector $fake,
        private readonly GmailConnector $gmail,
        private readonly string $clientId,
    ) {
    }

    private function delegate(): MailboxConnector
    {
        return '' === trim($this->clientId) ? $this->fake : $this->gmail;
    }

    public function authorizationUrl(string $state): string
    {
        return $this->delegate()->authorizationUrl($state);
    }

    public function exchangeCode(string $code): OAuthTokens
    {
        return $this->delegate()->exchangeCode($code);
    }

    public function revoke(string $refreshTokenPlain): void
    {
        $this->delegate()->revoke($refreshTokenPlain);
    }
}
