<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\OAuth;

use App\Mailbox\Application\Exception\MailboxConnectionFailed;
use App\Mailbox\Application\MailboxConnector;
use App\Mailbox\Application\OAuthTokens;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * ACL Microsoft OAuth 2.0 + Graph (D1 : Outlook, second fournisseur). Scopes
 * minimaux (Mail.Send + Mail.Read), client_secret strictement côté serveur.
 */
final class OutlookConnector implements MailboxConnector
{
    private const string AUTH_ENDPOINT = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
    private const string TOKEN_ENDPOINT = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    private const string PROFILE_ENDPOINT = 'https://graph.microsoft.com/v1.0/me';
    private const string SCOPES = 'offline_access https://graph.microsoft.com/Mail.Send https://graph.microsoft.com/Mail.Read';

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
            'response_mode' => 'query',
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
            throw MailboxConnectionFailed::because('Microsoft token exchange failed.', $e);
        }

        $accessToken = $payload['access_token'] ?? null;
        $refreshToken = $payload['refresh_token'] ?? null;
        if (!\is_string($accessToken) || !\is_string($refreshToken)) {
            throw MailboxConnectionFailed::because('Microsoft response is missing tokens.');
        }

        try {
            $profile = $this->httpClient->request('GET', self::PROFILE_ENDPOINT, [
                'headers' => ['Authorization' => 'Bearer '.$accessToken],
                'timeout' => 15,
            ])->toArray();
        } catch (ExceptionInterface $e) {
            throw MailboxConnectionFailed::because('Microsoft profile lookup failed.', $e);
        }

        $email = $profile['mail'] ?? $profile['userPrincipalName'] ?? null;
        if (!\is_string($email) || '' === $email) {
            throw MailboxConnectionFailed::because('Microsoft profile has no email address.');
        }

        return new OAuthTokens($accessToken, $refreshToken, $email);
    }

    public function revoke(string $refreshTokenPlain): void
    {
        // Graph n'expose pas de révocation par refresh token côté app :
        // l'utilisateur révoque depuis son compte Microsoft ; côté app, on efface les tokens.
    }
}
