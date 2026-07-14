<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Mailbox;

use App\Shared\Domain\ValueObject\TenantId;

interface MailboxRepository
{
    public function save(ConnectedMailbox $mailbox): void;

    /** La boîte du tenant (une seule en V1 — invariant handler + index, pas modèle). */
    public function findForTenant(TenantId $tenantId): ?ConnectedMailbox;
}
