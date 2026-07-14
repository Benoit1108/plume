<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Gateway;

use App\Mailbox\Application\Recipient;
use App\Mailbox\Application\RecipientResolver;
use App\Shared\Infrastructure\Persistence\Doctrine\HydratesRows;
use Doctrine\DBAL\Connection;

/**
 * Résolution du destinataire (tenant EXPLICITE, worker-safe) : le contact
 * DÉSIGNÉ de la piste d'abord, sinon le premier contact avec email.
 * RGPD à double niveau : organisation ET contact.
 */
final class DoctrineRecipientResolver implements RecipientResolver
{
    use HydratesRows;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function resolve(string $tenantId, string $leadId): ?Recipient
    {
        $row = $this->connection->fetchAssociative(
            'SELECT l.contact_id, o.do_not_contact, o.contacts
             FROM lead l JOIN organization o ON o.id = l.organization_id AND o.tenant_id = l.tenant_id
             WHERE l.tenant_id = :tenant AND l.id = :id',
            ['tenant' => $tenantId, 'id' => $leadId],
        );
        if (false === $row) {
            return null;
        }

        $organizationBlocked = $this->bool($row['do_not_contact'] ?? true);
        $contacts = \is_string($row['contacts'] ?? null) ? json_decode($row['contacts'], true) : null;
        if (!\is_array($contacts)) {
            return null;
        }

        $designated = $this->strOrNull($row, 'contact_id');
        $candidate = null;
        foreach ($contacts as $contact) {
            if (!\is_array($contact) || !\is_string($contact['email'] ?? null) || '' === $contact['email']) {
                continue;
            }
            if (null !== $designated && ($contact['id'] ?? null) === $designated) {
                $candidate = $contact;
                break;
            }
            $candidate ??= $contact;
        }
        if (null === $candidate) {
            return null;
        }

        $contactBlocked = $this->bool($candidate['doNotContact'] ?? false);
        $name = \is_string($candidate['fullName'] ?? null) && '' !== $candidate['fullName'] ? $candidate['fullName'] : null;

        // Le filtre de la boucle garantit un email non vide sur le candidat retenu.
        return new Recipient($candidate['email'], $name, !$organizationBlocked && !$contactBlocked);
    }
}
