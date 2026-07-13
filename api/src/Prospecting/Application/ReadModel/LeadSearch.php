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
}
