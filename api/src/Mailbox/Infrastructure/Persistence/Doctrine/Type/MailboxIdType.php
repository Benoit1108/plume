<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Persistence\Doctrine\Type;

use App\Mailbox\Domain\Mailbox\MailboxId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

/** Type DBAL pour le VO MailboxId (persisté en chaîne). */
final class MailboxIdType extends StringType
{
    public const string NAME = 'mailbox_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof MailboxId) {
            return $value->toString();
        }
        if (\is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('Expected MailboxId or string.');
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?MailboxId
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof MailboxId) {
            return $value;
        }
        if (\is_string($value)) {
            return MailboxId::fromString($value);
        }

        throw new \InvalidArgumentException('Expected MailboxId or string.');
    }
}
