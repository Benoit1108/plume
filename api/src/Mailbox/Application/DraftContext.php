<?php

declare(strict_types=1);

namespace App\Mailbox\Application;

/** Photo du brouillon à envoyer, vue depuis Mailbox. */
final class DraftContext
{
    public function __construct(
        public readonly string $leadId,
        public readonly string $type,
        public readonly ?string $subject,
        public readonly string $body,
        public readonly string $status,
    ) {
    }
}
