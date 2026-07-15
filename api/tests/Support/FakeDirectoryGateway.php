<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Sourcing\Application\Gateway\DirectoryGateway;

final class FakeDirectoryGateway implements DirectoryGateway
{
    /** @var list<array{organizationId: string, tenantId: string, name: string, type: string, website: ?string, segments: string[]}> */
    public array $created = [];

    /** @var string[] */
    private array $existing = [];

    public function addExisting(string $organizationId): void
    {
        $this->existing[] = $organizationId;
    }

    public function createOrganization(string $organizationId, string $tenantId, string $name, string $type, ?string $website, array $segments): void
    {
        $this->created[] = compact('organizationId', 'tenantId', 'name', 'type', 'website', 'segments');
        $this->existing[] = $organizationId;
    }

    public function organizationExists(string $organizationId, string $tenantId): bool
    {
        return \in_array($organizationId, $this->existing, true);
    }
}
