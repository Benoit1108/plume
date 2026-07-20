<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\ReadModel;

use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use App\Shared\Infrastructure\Persistence\Doctrine\HydratesRows;
use App\Sourcing\Application\ReadModel\AlertFeedList;
use App\Sourcing\Application\ReadModel\AlertFeedRow;
use Doctrine\DBAL\Connection;

/** Flux configurés du tenant courant (SQL direct, FAIL-CLOSED). */
final class DoctrineAlertFeedList implements AlertFeedList
{
    use HydratesRows;

    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function all(): array
    {
        $tenant = $this->tenantContext->require();

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, source, url, label, active, created_at
             FROM alert_feed WHERE tenant_id = :tenant ORDER BY created_at DESC',
            ['tenant' => $tenant->toString()],
        );

        return array_map(fn (array $row): AlertFeedRow => new AlertFeedRow(
            $this->str($row, 'id'),
            $this->str($row, 'source'),
            $this->str($row, 'url'),
            $this->str($row, 'label'),
            $this->bool($row['active'] ?? null), // Postgres 't'/'f' → bool
            $this->date($row, 'created_at')?->format(\DATE_ATOM) ?? '',
        ), $rows);
    }
}
