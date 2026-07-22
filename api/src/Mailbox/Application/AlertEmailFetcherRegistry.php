<?php

declare(strict_types=1);

namespace App\Mailbox\Application;

/** Une relève d'alertes par fournisseur — la boîte connectée choisit laquelle (cf. ReplyFetcherRegistry). */
interface AlertEmailFetcherRegistry
{
    public function fetcherFor(string $provider): AlertEmailFetcher;
}
