<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Crypto;

use App\Mailbox\Application\Exception\TokenCipherFailure;
use App\Mailbox\Application\TokenCipher;

/**
 * Chiffrement authentifié des tokens OAuth (ADR-0016) :
 * sodium secretbox (XSalsa20-Poly1305), nonce aléatoire préfixé, sortie base64.
 *
 * Clé : MAILBOX_ENCRYPTION_KEY (32 octets base64, dédiée — jamais la clé JWT).
 * Hors production, une clé vide est DÉRIVÉE d'APP_SECRET (rien à générer pour
 * dev/CI) ; en production, une clé explicite est OBLIGATOIRE (fail-fast au boot).
 */
final class SodiumTokenCipher implements TokenCipher
{
    private readonly string $key;

    public function __construct(string $encodedKey, string $appSecret, string $environment)
    {
        if ('' === $encodedKey) {
            if ('prod' === $environment) {
                throw new \LogicException('MAILBOX_ENCRYPTION_KEY is required in production (32 random bytes, base64 — see ADR-0016).');
            }
            // Dev/test/CI : clé déterministe dérivée du secret applicatif.
            $this->key = sodium_crypto_generichash($appSecret, '', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);

            return;
        }

        $decoded = base64_decode($encodedKey, true);
        if (false === $decoded || \SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== \strlen($decoded)) {
            throw new \LogicException('MAILBOX_ENCRYPTION_KEY must be 32 random bytes, base64-encoded (see ADR-0016).');
        }
        $this->key = $decoded;
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($plaintext, $nonce, $this->key));
    }

    public function decrypt(string $ciphertext): string
    {
        $decoded = base64_decode($ciphertext, true);
        if (false === $decoded || \strlen($decoded) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw TokenCipherFailure::because('Malformed ciphertext.');
        }

        $nonce = substr($decoded, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open(substr($decoded, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key);
        if (false === $plaintext) {
            throw TokenCipherFailure::because('Decryption failed (key changed or data corrupted).');
        }

        return $plaintext;
    }
}
