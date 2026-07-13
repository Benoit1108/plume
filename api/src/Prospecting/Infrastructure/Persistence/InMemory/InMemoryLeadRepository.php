<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\Persistence\InMemory;

use App\Prospecting\Domain\Lead\Exception\LeadNotFound;
use App\Prospecting\Domain\Lead\Lead;
use App\Prospecting\Domain\Lead\LeadId;
use App\Prospecting\Domain\Lead\LeadRepository;

/**
 * Double de test de la couche applicative (le binding de production pointe
 * DoctrineLeadRepository — cf. config/services.yaml).
 */
final class InMemoryLeadRepository implements LeadRepository
{
    /** @var array<string, Lead> */
    private array $leads = [];

    public function save(Lead $lead): void
    {
        $this->leads[$lead->id()->toString()] = $lead;
    }

    public function get(LeadId $id): Lead
    {
        return $this->leads[$id->toString()]
            ?? throw LeadNotFound::withId($id);
    }
}
