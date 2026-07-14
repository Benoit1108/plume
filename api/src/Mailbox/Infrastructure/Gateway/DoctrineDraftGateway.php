<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Gateway;

use App\Mailbox\Application\DraftContext;
use App\Mailbox\Application\DraftGateway;
use App\Shared\Infrastructure\Persistence\Doctrine\HydratesRows;
use Doctrine\DBAL\Connection;

/** Frontière Mailbox → Rédaction assistée : lecture SQL, tenant EXPLICITE (worker-safe). */
final class DoctrineDraftGateway implements DraftGateway
{
    use HydratesRows;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function context(string $tenantId, string $draftId): ?DraftContext
    {
        $row = $this->connection->fetchAssociative(
            'SELECT lead_id, type, subject, body, status FROM draft WHERE tenant_id = :tenant AND id = :id',
            ['tenant' => $tenantId, 'id' => $draftId],
        );
        if (false === $row) {
            return null;
        }

        return new DraftContext(
            leadId: $this->str($row, 'lead_id'),
            type: $this->str($row, 'type'),
            subject: $this->strOrNull($row, 'subject'),
            body: $this->str($row, 'body'),
            status: $this->str($row, 'status'),
        );
    }
}
