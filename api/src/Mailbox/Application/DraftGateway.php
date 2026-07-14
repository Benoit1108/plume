<?php

declare(strict_types=1);

namespace App\Mailbox\Application;

/** Frontière Mailbox → Rédaction assistée (tenant EXPLICITE : worker-safe). */
interface DraftGateway
{
    public function context(string $tenantId, string $draftId): ?DraftContext;
}
