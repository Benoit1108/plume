<?php

declare(strict_types=1);

namespace App\Mailbox\Application;

final class OutgoingMail
{
    /** @param ?string $threadKey fil d'origine — une relance part DANS le fil (M2.4) */
    public function __construct(
        public readonly string $toEmail,
        public readonly ?string $toName,
        public readonly ?string $subject,
        public readonly string $body,
        public readonly ?string $threadKey = null,
    ) {
    }
}
