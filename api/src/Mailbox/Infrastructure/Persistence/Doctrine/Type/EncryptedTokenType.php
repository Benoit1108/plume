<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Persistence\Doctrine\Type;

use App\Mailbox\Domain\Mailbox\EncryptedToken;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\TextType;

/** Type DBAL pour le VO EncryptedToken (ciphertext base64 en TEXT, nullable). */
final class EncryptedTokenType extends TextType
{
    public const string NAME = 'encrypted_token';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof EncryptedToken) {
            return $value->ciphertext();
        }
        if (\is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('Expected EncryptedToken or string.');
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?EncryptedToken
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if ($value instanceof EncryptedToken) {
            return $value;
        }
        if (\is_string($value)) {
            return EncryptedToken::fromCiphertext($value);
        }

        throw new \InvalidArgumentException('Expected EncryptedToken or string.');
    }
}
