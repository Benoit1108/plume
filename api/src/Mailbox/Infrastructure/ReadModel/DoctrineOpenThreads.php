<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\ReadModel;

use App\Mailbox\Application\OpenThreads;
use Doctrine\DBAL\Connection;

/**
 * Fils « ouverts » (tenant EXPLICITE, worker-safe) : envoyés, dont la piste
 * attend encore une réponse. Une piste en discussion sort de la relève —
 * c'est aussi ce qui rend la relève idempotente sans curseur.
 */
final class DoctrineOpenThreads implements OpenThreads
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function forTenant(string $tenantId): array
    {
        $rows = $this->connection->fetchAllKeyValue(
            "SELECT om.thread_key, om.lead_id
             FROM outbound_message om
             JOIN lead l ON l.id = om.lead_id AND l.tenant_id = om.tenant_id
             WHERE om.tenant_id = :tenant AND om.status = 'SENT' AND om.thread_key IS NOT NULL
             AND l.status IN ('CONTACTED', 'FOLLOWED_UP')",
            ['tenant' => $tenantId],
        );

        $threads = [];
        foreach ($rows as $threadKey => $leadId) {
            if (\is_string($threadKey) && \is_string($leadId)) {
                $threads[$threadKey] = $leadId;
            }
        }

        return $threads;
    }
}
