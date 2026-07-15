<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Token;

use App\Mailbox\Application\Exception\MailSendFailed;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Implémentation OAuth 2.0 `refresh_token` — paramétrée par l'endpoint token du
 * fournisseur. Deux instances câblées (Google / Microsoft), chacune injectée
 * dans le sender ET le fetcher du fournisseur : plus de `mintAccessToken` dupliqué.
 */
final class OAuthAccessTokenMinter implements AccessTokenMinter
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $tokenEndpoint,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
    }

    public function mint(string $refreshTokenPlain): string
    {
        try {
            $payload = $this->httpClient->request('POST', $this->tokenEndpoint, [
                'body' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $refreshTokenPlain,
                    'grant_type' => 'refresh_token',
                ],
                'timeout' => 15,
            ])->toArray();
        } catch (ExceptionInterface $e) {
            throw MailSendFailed::because('OAuth token refresh failed.', $e);
        }

        $accessToken = $payload['access_token'] ?? null;
        if (!\is_string($accessToken) || '' === $accessToken) {
            throw MailSendFailed::because('OAuth token refresh returned no access token.');
        }

        return $accessToken;
    }
}
