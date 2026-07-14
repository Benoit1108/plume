<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Persistence\Doctrine\Type;

use App\Mailbox\Domain\Outbound\OutboundMessageId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

/** Type DBAL pour le VO OutboundMessageId (persisté en chaîne). */
final class OutboundMessageIdType extends StringType
{
    public const string NAME = 'outbound_message_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof OutboundMessageId) {
            return $value->toString();
        }
        if (\is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('Expected OutboundMessageId or string.');
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?OutboundMessageId
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof OutboundMessageId) {
            return $value;
        }
        if (\is_string($value)) {
            return OutboundMessageId::fromString($value);
        }

        throw new \InvalidArgumentException('Expected OutboundMessageId or string.');
    }
}
