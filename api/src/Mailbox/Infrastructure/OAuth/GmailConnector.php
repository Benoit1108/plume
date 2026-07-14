<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\OAuth;

use App\Mailbox\Application\Exception\MailboxConnectionFailed;
use App\Mailbox\Application\MailboxConnector;
use App\Mailbox\Application\OAuthTokens;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * ACL Google OAuth 2.0 + Gmail (D1). Scopes MINIMAUX pour M2 : envoyer
 * (gmail.send) et lire les fils qu'on a initiés (gmail.readonly — filtré par
 * threading côté relève, ADR-0017). client_secret strictement côté serveur.
 */
final class GmailConnector implements MailboxConnector
{
    private const string AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const string TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const string REVOKE_ENDPOINT = 'https://oauth2.googleapis.com/revoke';
    private const string PROFILE_ENDPOINT = 'https://gmail.googleapis.com/gmail/v1/users/me/profile';
    private const string SCOPES = 'https://www.googleapis.com/auth/gmail.send https://www.googleapis.com/auth/gmail.readonly';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
    ) {
    }

    public function authorizationUrl(string $state): string
    {
        return self::AUTH_ENDPOINT.'?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'access_type' => 'offline',   // refresh token
            'prompt' => 'consent',        // garantit le refresh token à chaque reconnexion
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code): OAuthTokens
    {
        try {
            $payload = $this->httpClient->request('POST', self::TOKEN_ENDPOINT, [
                'body' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $this->redirectUri,
                ],
                'timeout' => 15,
            ])->toArray();
        } catch (ExceptionInterface $e) {
            throw MailboxConnectionFailed::because('Google token exchange failed.', $e);
        }

        $accessToken = $payload['access_token'] ?? null;
        $refreshToken = $payload['refresh_token'] ?? null;
        if (!\is_string($accessToken) || !\is_string($refreshToken)) {
            throw MailboxConnectionFailed::because('Google response is missing tokens.');
        }

        try {
            $profile = $this->httpClient->request('GET', self::PROFILE_ENDPOINT, [
                'headers' => ['Authorization' => 'Bearer '.$accessToken],
                'timeout' => 15,
            ])->toArray();
        } catch (ExceptionInterface $e) {
            throw MailboxConnectionFailed::because('Gmail profile lookup failed.', $e);
        }

        $email = $profile['emailAddress'] ?? null;
        if (!\is_string($email) || '' === $email) {
            throw MailboxConnectionFailed::because('Gmail profile has no email address.');
        }

        return new OAuthTokens($accessToken, $refreshToken, $email);
    }

    public function revoke(string $refreshTokenPlain): void
    {
        try {
            $this->httpClient->request('POST', self::REVOKE_ENDPOINT, [
                'body' => ['token' => $refreshTokenPlain],
                'timeout' => 15,
            ])->getStatusCode();
        } catch (ExceptionInterface) {
            // Best effort : un token déjà révoqué/expiré ne bloque pas la déconnexion.
        }
    }
}
