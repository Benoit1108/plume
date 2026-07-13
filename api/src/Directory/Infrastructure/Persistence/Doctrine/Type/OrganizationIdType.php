<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\Persistence\Doctrine\Type;

use App\Directory\Domain\Organization\OrganizationId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

final class OrganizationIdType extends StringType
{
    public const string NAME = 'organization_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof OrganizationId) {
            return $value->toString();
        }
        if (\is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('Expected OrganizationId or string.');
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?OrganizationId
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof OrganizationId) {
            return $value;
        }
        if (\is_string($value)) {
            return OrganizationId::fromString($value);
        }

        throw new \InvalidArgumentException('Expected OrganizationId or string.');
    }
}
