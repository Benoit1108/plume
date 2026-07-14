<?php

declare(strict_types=1);

namespace App\Mailbox\Application\Exception;

/** Déchiffrement impossible (clé changée, données corrompues) : reconnexion nécessaire. */
final class TokenCipherFailure extends \RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
