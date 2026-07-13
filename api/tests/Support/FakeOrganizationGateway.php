<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Prospecting\Application\OrganizationGateway;

/** Frontière Répertoire factice : on déclare les organisations connues et leur état RGPD. */
final class FakeOrganizationGateway implements OrganizationGateway
{
    /** @param array<string, array{doNotContact?: bool, contacts?: string[]}> $organizations */
    public function __construct(private array $organizations = [])
    {
    }

    public function add(string $organizationId, bool $doNotContact = false, string ...$contactIds): void
    {
        $this->organizations[$organizationId] = ['doNotContact' => $doNotContact, 'contacts' => array_values($contactIds)];
    }

    public function exists(string $organizationId): bool
    {
        return isset($this->organizations[$organizationId]);
    }

    public function isContactAllowed(string $organizationId): bool
    {
        return isset($this->organizations[$organizationId])
            && true !== ($this->organizations[$organizationId]['doNotContact'] ?? false);
    }

    public function hasContact(string $organizationId, string $contactId): bool
    {
        return \in_array($contactId, $this->organizations[$organizationId]['contacts'] ?? [], true);
    }
}
