<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Persistence\Doctrine\Type;

use App\Sourcing\Domain\CandidateLead\CandidateLeadId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

/** Type DBAL pour le VO CandidateLeadId (persisté en chaîne). */
final class CandidateLeadIdType extends StringType
{
    public const string NAME = 'candidate_lead_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof CandidateLeadId) {
            return $value->toString();
        }
        if (\is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('Expected CandidateLeadId or string.');
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?CandidateLeadId
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof CandidateLeadId) {
            return $value;
        }
        if (\is_string($value)) {
            return CandidateLeadId::fromString($value);
        }

        throw new \InvalidArgumentException('Expected CandidateLeadId or string.');
    }
}
