<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\Persistence\Doctrine\Type;

use App\Prospecting\Domain\Lead\LeadId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

/** Type DBAL pour le VO LeadId (persisté en chaîne). */
final class LeadIdType extends StringType
{
    public const string NAME = 'lead_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof LeadId) {
            return $value->toString();
        }
        if (\is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('Expected LeadId or string.');
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?LeadId
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof LeadId) {
            return $value;
        }
        if (\is_string($value)) {
            return LeadId::fromString($value);
        }

        throw new \InvalidArgumentException('Expected LeadId or string.');
    }
}
