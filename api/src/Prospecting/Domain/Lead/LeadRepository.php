<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead;

interface LeadRepository
{
    public function save(Lead $lead): void;

    /** @throws Exception\LeadNotFound si introuvable (dans le périmètre du tenant) */
    public function get(LeadId $id): Lead;

    /** Une piste non terminale (ni WON ni LOST) existe-t-elle déjà pour cette organisation ? */
    public function hasActiveForOrganization(string $organizationId): bool;
}
