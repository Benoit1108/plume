<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Fetcher;

use App\Mailbox\Application\ReplyFetcher;
use App\Mailbox\Application\ReplyFetcherRegistry;

/** Route vers la relève du fournisseur (factice sans identifiants). */
final class ProviderReplyFetcherRegistry implements ReplyFetcherRegistry
{
    public function __construct(
        private readonly FakeReplyFetcher $fake,
        private readonly GmailReplyFetcher $gmail,
        private readonly OutlookReplyFetcher $outlook,
        private readonly string $googleClientId,
        private readonly string $microsoftClientId,
    ) {
    }

    public function fetcherFor(string $provider): ReplyFetcher
    {
        return match ($provider) {
            'GMAIL' => '' === trim($this->googleClientId) ? $this->fake : $this->gmail,
            'OUTLOOK' => '' === trim($this->microsoftClientId) ? $this->fake : $this->outlook,
            default => $this->fake,
        };
    }
}
