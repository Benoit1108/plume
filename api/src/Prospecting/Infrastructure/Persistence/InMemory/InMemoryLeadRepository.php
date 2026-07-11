<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\Persistence\InMemory;

use App\Prospecting\Domain\Lead\Lead;
use App\Prospecting\Domain\Lead\LeadId;
use App\Prospecting\Domain\Lead\LeadRepository;

/**
 * M0 : implémentation en mémoire, pour compiler le conteneur et tester la couche
 * applicative. À remplacer par une implémentation Doctrine en M1 (mapping des agrégats).
 * Symfony crée automatiquement l'alias LeadRepository -> cette classe (implémentation unique).
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
            ?? throw new \RuntimeException(sprintf('Lead "%s" not found.', $id->toString()));
    }
}
