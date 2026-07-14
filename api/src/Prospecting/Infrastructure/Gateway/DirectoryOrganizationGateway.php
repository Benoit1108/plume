<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\Gateway;

use App\Prospecting\Application\OrganizationGateway;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use App\Shared\Infrastructure\Persistence\Doctrine\HydratesRows;
use Doctrine\DBAL\Connection;

/**
 * Frontière Prospection → Répertoire : lecture SQL directe, scoping tenant
 * explicite et FAIL-CLOSED (le SQLFilter ne s'applique pas au DBAL).
 */
final class DirectoryOrganizationGateway implements OrganizationGateway
{
    use HydratesRows;

    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function exists(string $organizationId): bool
    {
        return false !== $this->fetchRow($organizationId);
    }

    public function isContactAllowed(string $organizationId): bool
    {
        $row = $this->fetchRow($organizationId);

        return false !== $row && !$this->bool($row['do_not_contact'] ?? true);
    }

    public function hasContact(string $organizationId, string $contactId): bool
    {
        $row = $this->fetchRow($organizationId);
        if (false === $row || !\is_string($row['contacts'] ?? null)) {
            return false;
        }
        $contacts = json_decode($row['contacts'], true);
        if (!\is_array($contacts)) {
            return false;
        }
        foreach ($contacts as $contact) {
            if (\is_array($contact) && ($contact['id'] ?? null) === $contactId) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed>|false */
    private function fetchRow(string $organizationId): array|false
    {
        $tenant = $this->tenantContext->require();

        return $this->connection->fetchAssociative(
            'SELECT do_not_contact, contacts FROM organization WHERE tenant_id = :tenant AND id = :id',
            ['tenant' => $tenant->toString(), 'id' => $organizationId],
        );
    }
}
