<?php

declare(strict_types=1);

namespace App\Account\Application\Crypto;

/**
 * Chiffrement d'un secret du compte AU REPOS (même principe qu'ADR-0016 pour les tokens OAuth,
 * étendu au secret TOTP — ADR-0027 amendé). Le clair n'existe qu'en mémoire, le temps de générer
 * l'URI de provisionnement ou de vérifier un code ; en base, uniquement du chiffré.
 */
interface SecretCipher
{
    public function encrypt(string $plaintext): string;

    /** @throws SecretCipherFailure si le déchiffrement échoue (clé changée, données corrompues) */
    public function decrypt(string $ciphertext): string;
}
