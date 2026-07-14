<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\Persistence\Doctrine\Type;

use App\Drafting\Domain\Draft\DraftId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

/** Type DBAL pour le VO DraftId (persisté en chaîne). */
final class DraftIdType extends StringType
{
    public const string NAME = 'draft_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof DraftId) {
            return $value->toString();
        }
        if (\is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('Expected DraftId or string.');
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?DraftId
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof DraftId) {
            return $value;
        }
        if (\is_string($value)) {
            return DraftId::fromString($value);
        }

        throw new \InvalidArgumentException('Expected DraftId or string.');
    }
}
