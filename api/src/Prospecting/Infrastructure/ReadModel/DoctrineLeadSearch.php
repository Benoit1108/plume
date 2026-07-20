<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\ReadModel;

use App\Prospecting\Application\ReadModel\LeadPage;
use App\Prospecting\Application\ReadModel\LeadSearch;
use App\Prospecting\Application\ReadModel\LeadView;
use App\Prospecting\Domain\Lead\Exception\LeadNotFound;
use App\Prospecting\Domain\Lead\LeadId;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Doctrine\DBAL\Connection;

/**
 * Lecture du pipeline en SQL direct (DBAL) → vues immuables (ADR-0013).
 * Scoping tenant explicite et FAIL-CLOSED.
 */
final class DoctrineLeadSearch implements LeadSearch
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
        private readonly LeadViewMapper $mapper,
    ) {
    }

    public function search(?string $status, ?string $priority, ?string $segment, int $page, int $itemsPerPage): LeadPage
    {
        $tenant = $this->requireTenant();

        $where = ['l.tenant_id = :tenant'];
        $params = ['tenant' => $tenant];
        foreach (['status' => $status, 'priority' => $priority, 'segment' => $segment] as $column => $value) {
            if (null !== $value && '' !== $value) {
                $where[] = sprintf('l.%s = :%s', $column, $column);
                $params[$column] = $value;
            }
        }
        $whereSql = implode(' AND ', $where);

        $count = $this->connection->fetchOne(sprintf('SELECT COUNT(*) %s WHERE %s', LeadViewMapper::FROM, $whereSql), $params);
        $total = is_numeric($count) ? (int) $count : 0;

        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                "SELECT %s %s WHERE %s ORDER BY CASE l.priority WHEN 'HIGH' THEN 0 WHEN 'MEDIUM' THEN 1 ELSE 2 END, l.created_at DESC LIMIT %d OFFSET %d",
                LeadViewMapper::COLUMNS,
                LeadViewMapper::FROM,
                $whereSql,
                $itemsPerPage,
                ($page - 1) * $itemsPerPage,
            ),
            $params,
        );

        return new LeadPage(
            array_map(fn (array $row): LeadView => $this->mapper->map($row), $rows),
            $total,
            $page,
            $itemsPerPage,
        );
    }

    public function get(string $id): LeadView
    {
        $tenant = $this->requireTenant();

        $row = $this->connection->fetchAssociative(
            sprintf('SELECT %s %s WHERE l.tenant_id = :tenant AND l.id = :id', LeadViewMapper::COLUMNS, LeadViewMapper::FROM),
            ['tenant' => $tenant, 'id' => $id],
        );

        if (false === $row) {
            throw LeadNotFound::withId(LeadId::fromString($id));
        }

        return $this->mapper->map($row);
    }

    public function activeLeadIdForOrganization(string $tenantId, string $organizationId): ?string
    {
        // « Active » = non terminale (cf. index partiel uniq_lead_active_per_organization).
        // Tenant EXPLICITE (worker-safe) : la Prospection possède ce SQL, pas ses appelants.
        $id = $this->connection->fetchOne(
            "SELECT id FROM lead WHERE tenant_id = :tenant AND organization_id = :org AND status NOT IN ('WON', 'LOST') LIMIT 1",
            ['tenant' => $tenantId, 'org' => $organizationId],
        );

        return \is_string($id) ? $id : null;
    }

    private function requireTenant(): string
    {
        return $this->tenantContext->require()->toString();
    }
}
