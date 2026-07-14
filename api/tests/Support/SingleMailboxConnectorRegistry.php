<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Mailbox\Application\MailboxConnector;
use App\Mailbox\Application\MailboxConnectorRegistry;

final class SingleMailboxConnectorRegistry implements MailboxConnectorRegistry
{
    public function __construct(private readonly MailboxConnector $connector)
    {
    }

    public function connectorFor(string $provider): MailboxConnector
    {
        return $this->connector;
    }
}
