<?php

declare(strict_types=1);

namespace App\Account\Application\Crypto;

/** Le déchiffrement d'un secret a échoué (clé changée, données corrompues, format invalide). */
final class SecretCipherFailure extends \RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
