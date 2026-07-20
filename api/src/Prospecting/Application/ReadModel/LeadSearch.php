<?php

declare(strict_types=1);

namespace App\Prospecting\Application\ReadModel;

/**
 * Port de lecture du pipeline (côté query du CQRS).
 * L'implémentation scope TOUJOURS par tenant (fail-closed).
 */
interface LeadSearch
{
    public function search(?string $status, ?string $priority, ?string $segment, int $page, int $itemsPerPage): LeadPage;

    /** @throws \App\Prospecting\Domain\Lead\Exception\LeadNotFound */
    public function get(string $id): LeadView;

    /**
     * Id de la piste ACTIVE (non terminale) d'une organisation, s'il en existe une
     * (invariant « 1 piste active/org »). Tenant EXPLICITE, worker-safe.
     */
    public function activeLeadIdForOrganization(string $tenantId, string $organizationId): ?string;
}
