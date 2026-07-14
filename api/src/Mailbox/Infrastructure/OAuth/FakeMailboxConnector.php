<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\OAuth;

use App\Mailbox\Application\Exception\MailboxConnectionFailed;
use App\Mailbox\Application\MailboxConnector;
use App\Mailbox\Application\OAuthTokens;

/**
 * Connecteur factice — utilisé sans GOOGLE_CLIENT_ID (dev, tests, CI, E2E),
 * coût zéro et AUCUN consentement réel (pattern CannedMessageGenerator).
 * L'« autorisation » renvoie directement le callback local avec un code accepté.
 */
final class FakeMailboxConnector implements MailboxConnector
{
    public const string ACCEPTED_CODE = 'fake-oauth-code';

    public function __construct(private readonly string $redirectUri)
    {
    }

    public function authorizationUrl(string $state): string
    {
        return $this->redirectUri.'?'.http_build_query(['code' => self::ACCEPTED_CODE, 'state' => $state]);
    }

    public function exchangeCode(string $code): OAuthTokens
    {
        if (self::ACCEPTED_CODE !== $code) {
            throw MailboxConnectionFailed::because('Invalid fake code.');
        }

        return new OAuthTokens('fake-access-token', 'fake-refresh-token', 'traductrice@gmail.example');
    }

    public function revoke(string $refreshTokenPlain): void
    {
        // Rien à révoquer : personne n'a jamais consenti à rien.
    }
}
