<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead;

interface LeadRepository
{
    public function save(Lead $lead): void;

    /** @throws \RuntimeException si la piste est introuvable */
    public function get(LeadId $id): Lead;
}
