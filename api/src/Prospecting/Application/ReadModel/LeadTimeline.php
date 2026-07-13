<?php

declare(strict_types=1);

namespace App\Prospecting\Application\ReadModel;

/** Lecture du journal d'interactions d'une piste (fail-closed tenant). */
interface LeadTimeline
{
    /** @return InteractionView[] du plus récent au plus ancien */
    public function forLead(string $leadId): array;
}
