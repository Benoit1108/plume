<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Persistence\Doctrine\Type;

use App\Sourcing\Domain\AlertFeed\AlertFeedId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

/** Type DBAL pour le VO AlertFeedId (persisté en chaîne). */
final class AlertFeedIdType extends StringType
{
    public const string NAME = 'alert_feed_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof AlertFeedId) {
            return $value->toString();
        }
        if (\is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('Expected AlertFeedId or string.');
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?AlertFeedId
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof AlertFeedId) {
            return $value;
        }
        if (\is_string($value)) {
            return AlertFeedId::fromString($value);
        }

        throw new \InvalidArgumentException('Expected AlertFeedId or string.');
    }
}
