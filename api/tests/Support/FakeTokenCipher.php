<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Mailbox\Application\TokenCipher;

/** Chiffrement factice réversible (les tests d'application vérifient le FLUX, pas la crypto). */
final class FakeTokenCipher implements TokenCipher
{
    public function encrypt(string $plaintext): string
    {
        return 'enc('.$plaintext.')';
    }

    public function decrypt(string $ciphertext): string
    {
        return substr($ciphertext, 4, -1);
    }
}
