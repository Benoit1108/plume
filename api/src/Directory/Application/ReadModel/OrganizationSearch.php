<?php

declare(strict_types=1);

namespace App\Directory\Application\ReadModel;

/**
 * Port de lecture du Répertoire (côté query du CQRS).
 * L'implémentation scope TOUJOURS par tenant (fail-closed).
 */
interface OrganizationSearch
{
    public function search(?string $type, ?string $query, int $page, int $itemsPerPage): OrganizationPage;

    /** @throws \App\Directory\Domain\Organization\Exception\OrganizationNotFound */
    public function get(string $id): OrganizationView;

    /** Existence d'une organisation dans un tenant (tenant EXPLICITE, worker-safe). */
    public function existsById(string $organizationId, string $tenantId): bool;
}
