<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Drafting\Application\LeadContext;
use App\Drafting\Application\LeadGateway;

/** Frontière Prospection factice, vue depuis Drafting (tenant explicite). */
final class FakeLeadGateway implements LeadGateway
{
    /** @var array<string, LeadContext> clé = "tenant:lead" */
    private array $contexts = [];

    public function add(string $tenantId, string $leadId, LeadContext $context): void
    {
        $this->contexts[$tenantId.':'.$leadId] = $context;
    }

    public function context(string $tenantId, string $leadId): ?LeadContext
    {
        return $this->contexts[$tenantId.':'.$leadId] ?? null;
    }
}
