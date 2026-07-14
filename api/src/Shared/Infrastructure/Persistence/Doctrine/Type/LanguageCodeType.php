<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\ValueObject\LanguageCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

/** Type DBAL pour le VO LanguageCode (code ISO 639-1, 2 caractères). */
final class LanguageCodeType extends StringType
{
    public const string NAME = 'language_code';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof LanguageCode) {
            return $value->toString();
        }
        if (\is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('Expected LanguageCode or string.');
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?LanguageCode
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof LanguageCode) {
            return $value;
        }
        if (\is_string($value)) {
            return LanguageCode::fromString($value);
        }

        throw new \InvalidArgumentException('Expected LanguageCode or string.');
    }
}
