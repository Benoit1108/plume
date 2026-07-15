<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Sourcing\Application\Gateway\ProspectingGateway;

final class FakeProspectingGateway implements ProspectingGateway
{
    /** @var list<array{leadId: string, tenantId: string, organizationId: string, languagePair: string, source: string, priority: string, segment: string}> */
    public array $created = [];

    public function createLead(string $leadId, string $tenantId, string $organizationId, string $languagePair, string $source, string $priority, string $segment): void
    {
        $this->created[] = compact('leadId', 'tenantId', 'organizationId', 'languagePair', 'source', 'priority', 'segment');
    }
}
