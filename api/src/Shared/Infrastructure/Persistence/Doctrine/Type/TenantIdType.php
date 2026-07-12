<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

/** Type DBAL pour le VO TenantId (persisté en chaîne). */
final class TenantIdType extends StringType
{
    public const string NAME = 'tenant_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof TenantId) {
            return $value->toString();
        }
        if (\is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('Expected TenantId or string.');
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TenantId
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof TenantId) {
            return $value;
        }
        if (\is_string($value)) {
            return TenantId::fromString($value);
        }

        throw new \InvalidArgumentException('Expected TenantId or string.');
    }
}
