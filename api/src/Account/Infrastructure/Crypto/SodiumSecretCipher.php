<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Crypto;

use App\Account\Application\Crypto\SecretCipher;
use App\Account\Application\Crypto\SecretCipherFailure;

/**
 * Chiffrement authentifié du secret TOTP au repos (ADR-0027 amendé, même primitive qu'ADR-0016) :
 * sodium secretbox (XSalsa20-Poly1305), nonce aléatoire préfixé, sortie base64.
 *
 * Clé : TOTP_ENCRYPTION_KEY (32 octets base64, DÉDIÉE — jamais la clé mailbox ni la clé JWT).
 * Comme pour les tokens OAuth : hors production, une clé vide est DÉRIVÉE d'APP_SECRET (rien à
 * générer pour dev/CI), avec séparation de domaine (préfixe `totp:`) pour ne PAS coïncider avec la
 * clé dérivée du mailbox ; en production, une clé explicite est OBLIGATOIRE (fail-fast au boot).
 *
 * NB : le patron d'adaptateur sodium est dupliqué avec Mailbox (chaque contexte possède son port,
 * cf. `App\Mailbox\Application\TokenCipher`). Unification dans `Shared` = dette tracée (ADR-0022).
 */
final class SodiumSecretCipher implements SecretCipher
{
    private readonly string $key;

    public function __construct(string $encodedKey, string $appSecret, string $environment)
    {
        if ('' === $encodedKey) {
            if ('prod' === $environment) {
                throw new \LogicException('TOTP_ENCRYPTION_KEY is required in production (32 random bytes, base64 — see ADR-0027).');
            }
            // Dev/test/CI : clé déterministe dérivée du secret applicatif, séparée du domaine mailbox.
            $this->key = sodium_crypto_generichash('totp:'.$appSecret, '', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);

            return;
        }

        $decoded = base64_decode($encodedKey, true);
        if (false === $decoded || \SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== \strlen($decoded)) {
            throw new \LogicException('TOTP_ENCRYPTION_KEY must be 32 random bytes, base64-encoded (see ADR-0027).');
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
            throw SecretCipherFailure::because('Malformed ciphertext.');
        }

        $nonce = substr($decoded, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open(substr($decoded, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key);
        if (false === $plaintext) {
            throw SecretCipherFailure::because('Decryption failed (key changed or data corrupted).');
        }

        return $plaintext;
    }
}
