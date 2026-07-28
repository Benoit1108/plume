<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Security;

use App\Shared\Application\Clock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Jeton de vérification d'email SANS ÉTAT (V2.1b) : email + expiration signés par HMAC (clé =
 * APP_SECRET), au lieu d'une table (contrairement au reset de mot de passe qui exige l'usage unique
 * révocable). Vérifier deux fois est idempotent → l'absence d'état est ici acceptable. Format :
 * base64url(email|exp) . '.' . hmac. `hash_equals` (temps constant), expiration vérifiée.
 */
final class EmailVerificationSigner
{
    private const string TTL = '+24 hours';

    public function __construct(
        private readonly Clock $clock,
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    public function sign(string $email): string
    {
        $payload = self::b64(\sprintf('%s|%d', $email, $this->clock->now()->modify(self::TTL)->getTimestamp()));

        return $payload.'.'.hash_hmac('sha256', $payload, $this->secret);
    }

    /** Retourne l'email si le jeton est valide et non expiré, sinon null. */
    public function verify(string $token): ?string
    {
        $parts = explode('.', $token, 2);
        if (2 !== \count($parts)) {
            return null;
        }
        [$payload, $signature] = $parts;
        if (!hash_equals(hash_hmac('sha256', $payload, $this->secret), $signature)) {
            return null;
        }

        $decoded = self::unb64($payload);
        $fields = explode('|', $decoded, 2);
        if (2 !== \count($fields)) {
            return null;
        }
        [$email, $exp] = $fields;
        if (!ctype_digit($exp) || (int) $exp < $this->clock->now()->getTimestamp()) {
            return null;
        }

        return '' === $email ? null : $email;
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64(string $encoded): string
    {
        return (string) base64_decode(strtr($encoded, '-_', '+/'), true);
    }
}
