<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Mailbox\Domain\Mailbox\ConnectedMailbox;
use App\Mailbox\Domain\Mailbox\MailboxRepository;
use App\Shared\Domain\ValueObject\TenantId;

final class InMemoryMailboxRepository implements MailboxRepository
{
    /** @var array<string, ConnectedMailbox> clé = tenant */
    private array $byTenant = [];

    public function save(ConnectedMailbox $mailbox): void
    {
        $this->byTenant[$mailbox->tenantId()->toString()] = $mailbox;
    }

    public function findForTenant(TenantId $tenantId): ?ConnectedMailbox
    {
        return $this->byTenant[$tenantId->toString()] ?? null;
    }
}
