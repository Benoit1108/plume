<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\ReadModel;

use App\Directory\Application\ReadModel\ContactView;
use App\Directory\Application\ReadModel\OrganizationPage;
use App\Directory\Application\ReadModel\OrganizationSearch;
use App\Directory\Application\ReadModel\OrganizationView;
use App\Directory\Domain\Organization\Exception\OrganizationNotFound;
use App\Directory\Domain\Organization\OrganizationId;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use App\Shared\Infrastructure\Persistence\Doctrine\HydratesRows;
use Doctrine\DBAL\Connection;

/**
 * Lecture du Répertoire en SQL direct (DBAL) → vues immuables, sans hydratation ORM.
 * Le SQLFilter Doctrine ne s'applique pas au DBAL : le scoping tenant est explicite
 * et FAIL-CLOSED (pas de tenant en contexte = exception, jamais une requête globale).
 */
final class DoctrineOrganizationSearch implements OrganizationSearch
{
    use HydratesRows;

    private const COLUMNS = 'id, name, type, website, country, working_languages, segments, notes, do_not_contact, contacts';

    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function search(?string $type, ?string $query, int $page, int $itemsPerPage): OrganizationPage
    {
        $tenant = $this->requireTenant();

        $where = ['tenant_id = :tenant'];
        $params = ['tenant' => $tenant];
        if (null !== $type && '' !== $type) {
            $where[] = 'type = :type';
            $params['type'] = $type;
        }
        if (null !== $query && '' !== trim($query)) {
            $where[] = 'LOWER(name) LIKE :q';
            $params['q'] = '%'.mb_strtolower(trim($query)).'%';
        }
        $whereSql = implode(' AND ', $where);

        $count = $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM organization WHERE %s', $whereSql),
            $params,
        );
        $total = is_numeric($count) ? (int) $count : 0;

        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                'SELECT %s FROM organization WHERE %s ORDER BY name ASC LIMIT %d OFFSET %d',
                self::COLUMNS,
                $whereSql,
                $itemsPerPage,
                ($page - 1) * $itemsPerPage,
            ),
            $params,
        );

        return new OrganizationPage(
            array_map(fn (array $row): OrganizationView => $this->mapRow($row), $rows),
            $total,
            $page,
            $itemsPerPage,
        );
    }

    public function get(string $id): OrganizationView
    {
        $tenant = $this->requireTenant();

        $row = $this->connection->fetchAssociative(
            sprintf('SELECT %s FROM organization WHERE tenant_id = :tenant AND id = :id', self::COLUMNS),
            ['tenant' => $tenant, 'id' => $id],
        );

        if (false === $row) {
            throw OrganizationNotFound::withId(OrganizationId::fromString($id));
        }

        return $this->mapRow($row);
    }

    public function existsById(string $organizationId, string $tenantId): bool
    {
        // Tenant EXPLICITE (worker-safe) : le Répertoire possède ce SQL, pas ses appelants.
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM organization WHERE id = :id AND tenant_id = :tenant LIMIT 1',
            ['id' => $organizationId, 'tenant' => $tenantId],
        );
    }

    private function requireTenant(): string
    {
        return $this->tenantContext->require()->toString();
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): OrganizationView
    {
        return new OrganizationView(
            $this->str($row, 'id'),
            $this->str($row, 'name'),
            $this->str($row, 'type'),
            $this->strOrNull($row, 'website'),
            $this->strOrNull($row, 'country'),
            array_values(array_filter($this->jsonList($row, 'working_languages'), is_string(...))),
            array_values(array_filter($this->jsonList($row, 'segments'), is_string(...))),
            $this->strOrNull($row, 'notes'),
            $this->bool($row['do_not_contact'] ?? false),
            $this->contacts($row),
        );
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return ContactView[]
     */
    private function contacts(array $row): array
    {
        $contacts = [];
        foreach ($this->jsonList($row, 'contacts') as $contact) {
            if (!\is_array($contact) || !\is_string($contact['id'] ?? null)) {
                continue;
            }
            $contacts[] = new ContactView(
                $contact['id'],
                \is_string($contact['fullName'] ?? null) ? $contact['fullName'] : '',
                \is_string($contact['role'] ?? null) ? $contact['role'] : null,
                \is_string($contact['email'] ?? null) ? $contact['email'] : null,
                \is_string($contact['phone'] ?? null) ? $contact['phone'] : null,
                \is_string($contact['linkedinUrl'] ?? null) ? $contact['linkedinUrl'] : null,
                \is_string($contact['preferredLanguage'] ?? null) ? $contact['preferredLanguage'] : null,
                true === ($contact['doNotContact'] ?? false),
            );
        }

        return $contacts;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<mixed>
     */
    private function jsonList(array $row, string $key): array
    {
        $raw = $row[$key] ?? null;
        if (!\is_string($raw)) {
            return [];
        }
        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? array_values($decoded) : [];
    }
}
