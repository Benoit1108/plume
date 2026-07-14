<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\OAuth;

use App\Shared\Application\Clock;

/**
 * State OAuth anti-CSRF, SANS stockage serveur : tenant + expiration, signés
 * HMAC avec le secret applicatif. Le callback vérifie que le state revient
 * intact, non expiré, et qu'il appartient bien AU TENANT connecté.
 */
final class OAuthStateCodec
{
    private const int TTL_SECONDS = 600;

    public function __construct(
        private readonly string $appSecret,
        private readonly Clock $clock,
    ) {
    }

    public function issue(string $tenantId): string
    {
        $expiresAt = $this->clock->now()->getTimestamp() + self::TTL_SECONDS;
        $payload = $tenantId.'|'.$expiresAt.'|'.bin2hex(random_bytes(8));
        $signature = hash_hmac('sha256', $payload, $this->appSecret);

        return rtrim(strtr(base64_encode($payload.'|'.$signature), '+/', '-_'), '=');
    }

    public function isValidFor(string $state, string $tenantId): bool
    {
        $decoded = base64_decode(strtr($state, '-_', '+/'), true);
        if (false === $decoded) {
            return false;
        }
        $parts = explode('|', $decoded);
        if (4 !== \count($parts)) {
            return false;
        }
        [$stateTenant, $expiresAt, $nonce, $signature] = $parts;
        $expected = hash_hmac('sha256', $stateTenant.'|'.$expiresAt.'|'.$nonce, $this->appSecret);

        return hash_equals($expected, $signature)
            && hash_equals($tenantId, $stateTenant)
            && (int) $expiresAt >= $this->clock->now()->getTimestamp();
    }
}
