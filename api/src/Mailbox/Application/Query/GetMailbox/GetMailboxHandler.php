<?php

declare(strict_types=1);

namespace App\Mailbox\Application\Query\GetMailbox;

use App\Mailbox\Application\ReadModel\MailboxSettings;
use App\Mailbox\Application\ReadModel\MailboxView;
use App\Shared\Application\Query\QueryHandler;

final class GetMailboxHandler implements QueryHandler
{
    public function __construct(private readonly MailboxSettings $settings)
    {
    }

    public function __invoke(GetMailbox $query): ?MailboxView
    {
        return $this->settings->current();
    }
}
