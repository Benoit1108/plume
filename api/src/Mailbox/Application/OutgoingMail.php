<?php

declare(strict_types=1);

namespace App\Mailbox\Application;

final class OutgoingMail
{
    public function __construct(
        public readonly string $toEmail,
        public readonly ?string $toName,
        public readonly ?string $subject,
        public readonly string $body,
    ) {
    }
}
