<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Fetcher;

use App\Mailbox\Application\ReplyFetcher;

/** Sélection par l'env : GOOGLE_CLIENT_ID présent → Gmail réel, absent → factice. */
final class ReplyFetcherSelector implements ReplyFetcher
{
    public function __construct(
        private readonly FakeReplyFetcher $fake,
        private readonly GmailReplyFetcher $gmail,
        private readonly string $clientId,
    ) {
    }

    public function fetch(string $refreshTokenPlain, string $ownEmail, array $openThreads): array
    {
        $delegate = '' === trim($this->clientId) ? $this->fake : $this->gmail;

        return $delegate->fetch($refreshTokenPlain, $ownEmail, $openThreads);
    }
}
