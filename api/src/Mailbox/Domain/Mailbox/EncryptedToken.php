<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Mailbox;

use App\Shared\Domain\Exception\InvalidValue;

/**
 * Token OAuth CHIFFRÉ (ADR-0016). Le domaine ne voit jamais un token en clair :
 * le chiffrement/déchiffrement est un port applicatif (TokenCipher), et seul
 * l'adaptateur provider manipule le clair, en mémoire, au moment de l'appel.
 */
final class EncryptedToken
{
    private function __construct(private readonly string $ciphertext)
    {
        if ('' === $ciphertext) {
            throw InvalidValue::because('An encrypted token cannot be empty.');
        }
    }

    public static function fromCiphertext(string $ciphertext): self
    {
        return new self($ciphertext);
    }

    public function ciphertext(): string
    {
        return $this->ciphertext;
    }
}
