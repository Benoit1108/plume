<?php

declare(strict_types=1);

namespace App\Drafting\Application\ReadModel;

/** Port de lecture des brouillons (fail-closed tenant). */
interface DraftSearch
{
    /** @return DraftView[] du plus récent au plus ancien */
    public function forLead(string $leadId): array;

    /** @throws \App\Drafting\Domain\Draft\Exception\DraftNotFound */
    public function get(string $id): DraftView;
}
