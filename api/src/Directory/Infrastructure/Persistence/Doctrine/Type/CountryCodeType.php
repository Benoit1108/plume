<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\ValueObject\CountryCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

final class CountryCodeType extends StringType
{
    public const string NAME = 'country_code';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof CountryCode) {
            return $value->toString();
        }
        if (\is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('Expected CountryCode or string.');
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?CountryCode
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof CountryCode) {
            return $value;
        }
        if (\is_string($value)) {
            return CountryCode::fromString($value);
        }

        throw new \InvalidArgumentException('Expected CountryCode or string.');
    }
}
