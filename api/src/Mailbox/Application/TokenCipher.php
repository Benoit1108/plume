<?php

declare(strict_types=1);

namespace App\Mailbox\Application;

/**
 * Chiffrement des tokens OAuth au repos (ADR-0016). Le clair n'existe qu'en
 * mémoire, le temps d'un appel provider — jamais en base, jamais en log.
 */
interface TokenCipher
{
    public function encrypt(string $plaintext): string;

    /** @throws Exception\TokenCipherFailure si le déchiffrement échoue (clé changée, données corrompues) */
    public function decrypt(string $ciphertext): string;
}
