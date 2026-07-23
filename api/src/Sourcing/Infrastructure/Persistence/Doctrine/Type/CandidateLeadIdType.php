<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractStringIdType;
use App\Sourcing\Domain\CandidateLead\CandidateLeadId;

/** Type DBAL pour le VO CandidateLeadId (persisté en chaîne). */
final class CandidateLeadIdType extends AbstractStringIdType
{
    public const string NAME = 'candidate_lead_id';

    public function getName(): string
    {
        return self::NAME;
    }

    protected function idClass(): string
    {
        return CandidateLeadId::class;
    }
}
