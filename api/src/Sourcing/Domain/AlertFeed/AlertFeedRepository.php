<?php

declare(strict_types=1);

namespace App\Sourcing\Domain\AlertFeed;

use App\Shared\Domain\ValueObject\TenantId;

interface AlertFeedRepository
{
    public function save(AlertFeed $feed): void;

    public function find(AlertFeedId $id): ?AlertFeed;

    public function remove(AlertFeed $feed): void;

    /**
     * Flux ACTIFS d'un tenant (tenant EXPLICITE, fail-closed : la relève tourne hors requête).
     *
     * @return list<AlertFeed>
     */
    public function activeForTenant(TenantId $tenantId): array;

    /** Nombre total de flux (actifs ou non) d'un tenant — pour le plafond de quota. */
    public function countForTenant(TenantId $tenantId): int;
}
