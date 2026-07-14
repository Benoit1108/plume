<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Mailbox\Application\MailSender;
use App\Mailbox\Application\MailSenderRegistry;

final class SingleMailSenderRegistry implements MailSenderRegistry
{
    public function __construct(private readonly MailSender $sender)
    {
    }

    public function senderFor(string $provider): MailSender
    {
        return $this->sender;
    }
}
