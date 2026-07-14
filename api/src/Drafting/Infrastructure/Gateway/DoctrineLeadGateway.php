<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\Gateway;

use App\Drafting\Application\LeadContext;
use App\Drafting\Application\LeadGateway;
use App\Shared\Infrastructure\Persistence\Doctrine\HydratesRows;
use Doctrine\DBAL\Connection;

/**
 * Frontière Drafting → Prospection/Répertoire : lecture SQL directe.
 * Tenant EXPLICITE (paramètre, jamais le contexte de requête) : ce gateway
 * est aussi appelé depuis le worker, où TenantContext n'existe pas.
 */
final class DoctrineLeadGateway implements LeadGateway
{
    use HydratesRows;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function context(string $tenantId, string $leadId): ?LeadContext
    {
        $row = $this->connection->fetchAssociative(
            'SELECT l.organization_id, l.contact_id, l.language_pair, l.segment, l.status,
                    o.name AS organization_name, o.do_not_contact, o.contacts
             FROM lead l
             JOIN organization o ON o.id = l.organization_id AND o.tenant_id = l.tenant_id
             WHERE l.tenant_id = :tenant AND l.id = :id',
            ['tenant' => $tenantId, 'id' => $leadId],
        );
        if (false === $row) {
            return null;
        }

        $contacts = \is_string($row['contacts'] ?? null) ? json_decode($row['contacts'], true) : null;
        $contactName = null;
        $contactBlocked = false;
        if (\is_array($contacts) && \is_string($row['contact_id'] ?? null)) {
            foreach ($contacts as $contact) {
                if (\is_array($contact) && ($contact['id'] ?? null) === $row['contact_id']) {
                    $contactName = \is_string($contact['fullName'] ?? null) ? $contact['fullName'] : null;
                    $contactBlocked = $this->bool($contact['doNotContact'] ?? false);
                    break;
                }
            }
        }

        return new LeadContext(
            organizationId: $this->str($row, 'organization_id'),
            organizationName: $this->str($row, 'organization_name'),
            segment: $this->str($row, 'segment'),
            languagePair: $this->str($row, 'language_pair'),
            status: $this->str($row, 'status'),
            contactName: $contactName,
            contactAllowed: !$this->bool($row['do_not_contact'] ?? true) && !$contactBlocked,
        );
    }
}
