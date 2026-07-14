<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\ValueObject\EmailAddress;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

/** Type DBAL pour le VO EmailAddress (persisté en chaîne). */
final class EmailAddressType extends StringType
{
    public const string NAME = 'email_address';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof EmailAddress) {
            return $value->toString();
        }
        if (\is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('Expected EmailAddress or string.');
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?EmailAddress
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof EmailAddress) {
            return $value;
        }
        if (\is_string($value)) {
            return EmailAddress::fromString($value);
        }

        throw new \InvalidArgumentException('Expected EmailAddress or string.');
    }
}
