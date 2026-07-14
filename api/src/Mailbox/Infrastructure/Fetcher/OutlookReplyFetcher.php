<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Fetcher;

use App\Mailbox\Application\Exception\MailSendFailed;
use App\Mailbox\Application\IncomingReply;
use App\Mailbox\Application\ReplyFetcher;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** ACL Graph (relève) : messages du fil (conversationId), bodyPreview TEXTE borné. */
final class OutlookReplyFetcher implements ReplyFetcher
{
    private const string TOKEN_ENDPOINT = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    private const string MESSAGES_ENDPOINT = 'https://graph.microsoft.com/v1.0/me/messages';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
    }

    public function fetch(string $refreshTokenPlain, string $ownEmail, array $openThreads): array
    {
        $accessToken = $this->mintAccessToken($refreshTokenPlain);
        $replies = [];

        foreach ($openThreads as $threadKey => $leadId) {
            try {
                $payload = $this->httpClient->request('GET', self::MESSAGES_ENDPOINT, [
                    'headers' => ['Authorization' => 'Bearer '.$accessToken],
                    'query' => [
                        '$filter' => sprintf("conversationId eq '%s'", str_replace("'", "''", $threadKey)),
                        '$select' => 'from,bodyPreview',
                        '$top' => '20',
                    ],
                    'timeout' => 15,
                ])->toArray();
            } catch (ExceptionInterface) {
                continue; // fil supprimé côté boîte : on n'en fait pas un drame
            }

            $messages = $payload['value'] ?? null;
            if (!\is_array($messages)) {
                continue;
            }
            foreach ($messages as $message) {
                if (!\is_array($message) || !$this->isFromSomeoneElse($message, $ownEmail)) {
                    continue;
                }
                $preview = $message['bodyPreview'] ?? null;
                $replies[] = new IncomingReply($leadId, $threadKey, mb_substr(\is_string($preview) ? trim($preview) : '', 0, 280));
                break;
            }
        }

        return $replies;
    }

    /** @param array<mixed, mixed> $message */
    private function isFromSomeoneElse(array $message, string $ownEmail): bool
    {
        $from = $message['from'] ?? null;
        $emailAddress = \is_array($from) ? ($from['emailAddress'] ?? null) : null;
        $address = \is_array($emailAddress) ? ($emailAddress['address'] ?? null) : null;

        return \is_string($address) && '' !== $address
            && mb_strtolower($address) !== mb_strtolower($ownEmail);
    }

    private function mintAccessToken(string $refreshTokenPlain): string
    {
        try {
            $payload = $this->httpClient->request('POST', self::TOKEN_ENDPOINT, [
                'body' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $refreshTokenPlain,
                    'grant_type' => 'refresh_token',
                ],
                'timeout' => 15,
            ])->toArray();
        } catch (ExceptionInterface $e) {
            throw MailSendFailed::because('Microsoft token refresh failed.', $e);
        }

        $accessToken = $payload['access_token'] ?? null;
        if (!\is_string($accessToken) || '' === $accessToken) {
            throw MailSendFailed::because('Microsoft token refresh returned no access token.');
        }

        return $accessToken;
    }
}
