<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Mailbox\Application\ReplyFetcher;
use App\Mailbox\Application\ReplyFetcherRegistry;

final class SingleReplyFetcherRegistry implements ReplyFetcherRegistry
{
    public function __construct(private readonly ReplyFetcher $fetcher)
    {
    }

    public function fetcherFor(string $provider): ReplyFetcher
    {
        return $this->fetcher;
    }
}
