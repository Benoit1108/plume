<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\ReadModel;

use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use App\Shared\Infrastructure\Persistence\Doctrine\HydratesRows;
use App\Sourcing\Application\ReadModel\CandidateQueue;
use App\Sourcing\Application\ReadModel\CandidateQueueRow;
use Doctrine\DBAL\Connection;

/** File de tri : annonces PENDING du tenant courant (SQL direct, FAIL-CLOSED). */
final class DoctrineCandidateQueue implements CandidateQueue
{
    use HydratesRows;

    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function pending(): array
    {
        $tenant = $this->tenantContext->require();

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, source, status, title, organization_name, language_pair, url, excerpt, posted_at, ingested_at
             FROM candidate_lead WHERE tenant_id = :tenant AND status = :status ORDER BY ingested_at DESC',
            ['tenant' => $tenant->toString(), 'status' => 'PENDING'],
        );

        return array_map(fn (array $row): CandidateQueueRow => new CandidateQueueRow(
            $this->str($row, 'id'),
            $this->str($row, 'source'),
            $this->str($row, 'status'),
            $this->str($row, 'title'),
            $this->strOrNull($row, 'organization_name'),
            $this->strOrNull($row, 'language_pair'),
            $this->strOrNull($row, 'url'),
            $this->strOrNull($row, 'excerpt'),
            $this->date($row, 'posted_at')?->format(\DATE_ATOM),
            $this->date($row, 'ingested_at')?->format(\DATE_ATOM) ?? '',
        ), $rows);
    }
}
