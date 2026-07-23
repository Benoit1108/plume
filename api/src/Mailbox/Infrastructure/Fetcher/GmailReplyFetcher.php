<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Fetcher;

use App\Mailbox\Application\IncomingReply;
use App\Mailbox\Application\ReplyFetcher;
use App\Mailbox\Application\ReplyPreviewCleaner;
use App\Mailbox\Infrastructure\Token\AccessTokenMinter;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * ACL Gmail (relève, ADR-0017) : ne lit QUE nos fils ouverts (threads.get),
 * retient le premier message d'AUTRUI, et n'en garde que le `snippet`
 * (texte fourni par Gmail — jamais de HTML), borné à 280 caractères.
 */
final class GmailReplyFetcher implements ReplyFetcher
{
    private const string THREAD_ENDPOINT = 'https://gmail.googleapis.com/gmail/v1/users/me/threads/%s?format=metadata&metadataHeaders=From';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AccessTokenMinter $tokenMinter,
    ) {
    }

    public function fetch(string $refreshTokenPlain, string $ownEmail, array $openThreads): array
    {
        $accessToken = $this->tokenMinter->mint($refreshTokenPlain);
        $replies = [];

        foreach ($openThreads as $threadKey => $leadId) {
            try {
                $thread = $this->httpClient->request('GET', sprintf(self::THREAD_ENDPOINT, urlencode($threadKey)), [
                    'headers' => ['Authorization' => 'Bearer '.$accessToken],
                    'timeout' => 15,
                ])->toArray();
            } catch (ExceptionInterface) {
                continue; // fil supprimé côté boîte : on n'en fait pas un drame
            }

            $messages = $thread['messages'] ?? null;
            if (!\is_array($messages)) {
                continue;
            }
            foreach ($messages as $message) {
                if (!\is_array($message) || !$this->isFromSomeoneElse($message, $ownEmail)) {
                    continue;
                }
                $snippet = $message['snippet'] ?? null;
                $preview = ReplyPreviewCleaner::clean(\is_string($snippet) ? $snippet : '');
                $replies[] = new IncomingReply($leadId, $threadKey, $preview);
                break; // une réponse suffit : la piste passe en discussion
            }
        }

        return $replies;
    }

    /** @param array<mixed, mixed> $message */
    private function isFromSomeoneElse(array $message, string $ownEmail): bool
    {
        $payload = $message['payload'] ?? null;
        $headers = \is_array($payload) ? ($payload['headers'] ?? null) : null;
        if (!\is_array($headers)) {
            return false;
        }
        foreach ($headers as $header) {
            if (\is_array($header) && 'From' === ($header['name'] ?? null) && \is_string($header['value'] ?? null)) {
                return !str_contains(mb_strtolower($header['value']), mb_strtolower($ownEmail));
            }
        }

        return false;
    }
}
