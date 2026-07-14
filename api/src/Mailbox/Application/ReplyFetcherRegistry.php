<?php

declare(strict_types=1);

namespace App\Mailbox\Application;

/** Une relève par fournisseur — la boîte connectée choisit laquelle. */
interface ReplyFetcherRegistry
{
    public function fetcherFor(string $provider): ReplyFetcher;
}
