<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\ReadModel;

use App\Prospecting\Application\ReadModel\LeadPage;
use App\Prospecting\Application\ReadModel\LeadSearch;
use App\Prospecting\Application\ReadModel\LeadView;
use App\Prospecting\Domain\Lead\Exception\LeadNotFound;
use App\Prospecting\Domain\Lead\LeadId;
use App\Prospecting\Domain\Lead\PipelineStatus;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Doctrine\DBAL\Connection;

/**
 * Lecture du pipeline en SQL direct (DBAL) → vues immuables (ADR-0013).
 * Scoping tenant explicite et FAIL-CLOSED.
 */
final class DoctrineLeadSearch implements LeadSearch
{
    private const COLUMNS = 'l.id, l.organization_id, l.contact_id, l.language_pair, l.source, l.priority, l.segment, l.status, l.created_at, l.last_contacted_at, l.last_reply_at, o.name AS organization_name';
    private const FROM = 'FROM lead l LEFT JOIN organization o ON o.id = l.organization_id AND o.tenant_id = l.tenant_id';

    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
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

        $count = $this->connection->fetchOne(sprintf('SELECT COUNT(*) %s WHERE %s', self::FROM, $whereSql), $params);
        $total = is_numeric($count) ? (int) $count : 0;

        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                "SELECT %s %s WHERE %s ORDER BY CASE l.priority WHEN 'HIGH' THEN 0 WHEN 'MEDIUM' THEN 1 ELSE 2 END, l.created_at DESC LIMIT %d OFFSET %d",
                self::COLUMNS,
                self::FROM,
                $whereSql,
                $itemsPerPage,
                ($page - 1) * $itemsPerPage,
            ),
            $params,
        );

        return new LeadPage(
            array_map(fn (array $row): LeadView => $this->mapRow($row), $rows),
            $total,
            $page,
            $itemsPerPage,
        );
    }

    public function get(string $id): LeadView
    {
        $tenant = $this->requireTenant();

        $row = $this->connection->fetchAssociative(
            sprintf('SELECT %s %s WHERE l.tenant_id = :tenant AND l.id = :id', self::COLUMNS, self::FROM),
            ['tenant' => $tenant, 'id' => $id],
        );

        if (false === $row) {
            throw LeadNotFound::withId(LeadId::fromString($id));
        }

        return $this->mapRow($row);
    }

    private function requireTenant(): string
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new \LogicException('Lead read model queried without tenant in context — refusing to run an unscoped query.');
        }

        return $tenant->toString();
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): LeadView
    {
        $status = PipelineStatus::from($this->str($row, 'status'));

        return new LeadView(
            $this->str($row, 'id'),
            $this->str($row, 'organization_id'),
            $this->str($row, 'organization_name'),
            $this->strOrNull($row, 'contact_id'),
            $this->str($row, 'language_pair'),
            $this->str($row, 'source'),
            $this->str($row, 'priority'),
            $this->str($row, 'segment'),
            $status->value,
            $this->allowedActions($status),
            $this->date($row, 'created_at') ?? new \DateTimeImmutable('@0'),
            $this->date($row, 'last_contacted_at'),
            $this->date($row, 'last_reply_at'),
        );
    }

    /**
     * Statut → actions proposables à l'UI. FOLLOWED_UP/TO_CONTACT ne sont pas des
     * actions directes (les relances arrivent en M1.3) ; depuis PAUSED, seule la
     * reprise a du sens (retour au statut mémorisé).
     *
     * @return string[]
     */
    private function allowedActions(PipelineStatus $status): array
    {
        if (PipelineStatus::PAUSED === $status) {
            return ['resume'];
        }

        $actionByTarget = [
            PipelineStatus::CONTACTED->value => 'contact',
            PipelineStatus::IN_DISCUSSION->value => 'reply',
            PipelineStatus::SAMPLE_TEST->value => 'sample-test',
            PipelineStatus::WON->value => 'win',
            PipelineStatus::LOST->value => 'lose',
            PipelineStatus::PAUSED->value => 'pause',
        ];

        $actions = [];
        foreach ($status->allowedTransitions() as $target) {
            if (isset($actionByTarget[$target->value])) {
                $actions[] = $actionByTarget[$target->value];
            }
        }

        return $actions;
    }

    /** @param array<string, mixed> $row */
    private function str(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        return \is_string($value) ? $value : '';
    }

    /** @param array<string, mixed> $row */
    private function strOrNull(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }

    /** @param array<string, mixed> $row */
    private function date(array $row, string $key): ?\DateTimeImmutable
    {
        $value = $row[$key] ?? null;

        return \is_string($value) && '' !== $value ? new \DateTimeImmutable($value) : null;
    }
}
