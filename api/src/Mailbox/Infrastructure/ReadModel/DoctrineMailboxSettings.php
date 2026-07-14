<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\ReadModel;

use App\Mailbox\Application\ReadModel\MailboxSettings;
use App\Mailbox\Application\ReadModel\MailboxView;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use App\Shared\Infrastructure\Persistence\Doctrine\HydratesRows;
use Doctrine\DBAL\Connection;

/**
 * Lecture de la boîte du tenant (SQL direct, FAIL-CLOSED tenant — ADR-0013).
 * Les colonnes de tokens ne sont JAMAIS sélectionnées ici.
 */
final class DoctrineMailboxSettings implements MailboxSettings
{
    use HydratesRows;

    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function current(): ?MailboxView
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, provider, email_address, status, failure_reason, connected_at, last_sync_at
             FROM connected_mailbox WHERE tenant_id = :tenant',
            ['tenant' => $this->tenantContext->require()->toString()],
        );
        if (false === $row) {
            return null;
        }

        return new MailboxView(
            id: $this->str($row, 'id'),
            provider: $this->str($row, 'provider'),
            emailAddress: $this->str($row, 'email_address'),
            status: $this->str($row, 'status'),
            failureReason: $this->strOrNull($row, 'failure_reason'),
            connectedAt: $this->date($row, 'connected_at') ?? new \DateTimeImmutable('@0'),
            lastSyncAt: $this->date($row, 'last_sync_at'),
        );
    }
}
