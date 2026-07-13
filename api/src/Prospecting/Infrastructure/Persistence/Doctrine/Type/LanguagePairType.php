<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\ValueObject\LanguagePair;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

/** Type DBAL pour le VO LanguagePair (persisté en chaîne canonique "en>fr"). */
final class LanguagePairType extends StringType
{
    public const string NAME = 'language_pair';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof LanguagePair) {
            return $value->toString();
        }
        if (\is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('Expected LanguagePair or string.');
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?LanguagePair
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof LanguagePair) {
            return $value;
        }
        if (\is_string($value)) {
            return LanguagePair::fromString($value);
        }

        throw new \InvalidArgumentException('Expected LanguagePair or string.');
    }
}
