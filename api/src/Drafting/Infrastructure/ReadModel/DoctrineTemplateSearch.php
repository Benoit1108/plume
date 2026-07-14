<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\ReadModel;

use App\Drafting\Application\ReadModel\TemplateSearch;
use App\Drafting\Application\ReadModel\TemplateView;
use App\Drafting\Domain\Template\Exception\TemplateNotFound;
use App\Drafting\Domain\Template\TemplateId;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Doctrine\DBAL\Connection;

/** Lecture des gabarits (SQL direct, FAIL-CLOSED tenant — ADR-0013). */
final class DoctrineTemplateSearch implements TemplateSearch
{
    private const string SELECT = 'SELECT id, name, type, segment, language, subject, body FROM template';

    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function all(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            self::SELECT.' WHERE tenant_id = :tenant ORDER BY name ASC, id ASC',
            ['tenant' => $this->tenant()],
        );

        return array_map($this->map(...), $rows);
    }

    public function get(string $id): TemplateView
    {
        $row = $this->connection->fetchAssociative(
            self::SELECT.' WHERE tenant_id = :tenant AND id = :id',
            ['tenant' => $this->tenant(), 'id' => $id],
        );
        if (false === $row) {
            throw TemplateNotFound::withId(TemplateId::fromString($id));
        }

        return $this->map($row);
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): TemplateView
    {
        return new TemplateView(
            id: $this->str($row, 'id'),
            name: $this->str($row, 'name'),
            type: $this->str($row, 'type'),
            segment: $this->str($row, 'segment'),
            language: $this->str($row, 'language'),
            subject: \is_string($row['subject'] ?? null) && '' !== $row['subject'] ? $row['subject'] : null,
            body: $this->str($row, 'body'),
        );
    }

    /** @param array<string, mixed> $row */
    private function str(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        return \is_string($value) ? $value : '';
    }

    private function tenant(): string
    {
        $tenant = $this->tenantContext->get()
            ?? throw new \LogicException('Template search queried without tenant in context — refusing to run an unscoped query.');

        return $tenant->toString();
    }
}
