<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\ReadModel;

use App\Drafting\Application\ReadModel\DraftSearch;
use App\Drafting\Application\ReadModel\DraftView;
use App\Drafting\Domain\Draft\DraftId;
use App\Drafting\Domain\Draft\Exception\DraftNotFound;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use App\Shared\Infrastructure\Persistence\Doctrine\HydratesRows;
use Doctrine\DBAL\Connection;

/** Lecture des brouillons (SQL direct, FAIL-CLOSED tenant — ADR-0013). */
final class DoctrineDraftSearch implements DraftSearch
{
    use HydratesRows;

    private const string SELECT = 'SELECT id, lead_id, type, target_language, template_id,
        subject, body, status, failure_reason, created_at, updated_at FROM draft';

    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function forLead(string $leadId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            self::SELECT.' WHERE tenant_id = :tenant AND lead_id = :lead ORDER BY created_at DESC, id DESC',
            ['tenant' => $this->tenant(), 'lead' => $leadId],
        );

        return array_map($this->map(...), $rows);
    }

    public function get(string $id): DraftView
    {
        $row = $this->connection->fetchAssociative(
            self::SELECT.' WHERE tenant_id = :tenant AND id = :id',
            ['tenant' => $this->tenant(), 'id' => $id],
        );
        if (false === $row) {
            throw DraftNotFound::withId(DraftId::fromString($id));
        }

        return $this->map($row);
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): DraftView
    {
        return new DraftView(
            id: $this->str($row, 'id'),
            leadId: $this->str($row, 'lead_id'),
            type: $this->str($row, 'type'),
            targetLanguage: $this->str($row, 'target_language'),
            templateId: $this->strOrNull($row, 'template_id'),
            subject: $this->strOrNull($row, 'subject'),
            body: $this->str($row, 'body'),
            status: $this->str($row, 'status'),
            failureReason: $this->strOrNull($row, 'failure_reason'),
            createdAt: new \DateTimeImmutable($this->str($row, 'created_at')),
            updatedAt: new \DateTimeImmutable($this->str($row, 'updated_at')),
        );
    }

    private function tenant(): string
    {
        $tenant = $this->tenantContext->require();

        return $tenant->toString();
    }
}
