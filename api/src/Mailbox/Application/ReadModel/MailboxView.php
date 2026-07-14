<?php

declare(strict_types=1);

namespace App\Mailbox\Application\ReadModel;

/** Vue de la boîte connectée — immuable (ADR-0013), jamais de token dedans. */
final class MailboxView
{
    public function __construct(
        public readonly string $id,
        public readonly string $provider,
        public readonly string $emailAddress,
        public readonly string $status,
        public readonly ?string $failureReason,
        public readonly \DateTimeImmutable $connectedAt,
        public readonly ?\DateTimeImmutable $lastSyncAt,
    ) {
    }
}
