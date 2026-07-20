<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Sourcing\Application\Gateway\ProspectingGateway;

final class FakeProspectingGateway implements ProspectingGateway
{
    /** @var list<array{leadId: string, tenantId: string, organizationId: string, languagePair: string, source: string, priority: string, segment: string}> */
    public array $created = [];

    /** @var list<array{leadId: string, text: string}> */
    public array $notes = [];

    private ?string $activeLeadId = null;

    /** Configure une piste active existante pour l'organisation (fusion). */
    public function withActiveLead(string $leadId): void
    {
        $this->activeLeadId = $leadId;
    }

    public function createLead(string $leadId, string $tenantId, string $organizationId, string $languagePair, string $source, string $priority, string $segment): void
    {
        $this->created[] = compact('leadId', 'tenantId', 'organizationId', 'languagePair', 'source', 'priority', 'segment');
    }

    public function activeLeadId(string $tenantId, string $organizationId): ?string
    {
        return $this->activeLeadId;
    }

    public function annotateLead(string $leadId, string $text): void
    {
        $this->notes[] = compact('leadId', 'text');
    }
}
