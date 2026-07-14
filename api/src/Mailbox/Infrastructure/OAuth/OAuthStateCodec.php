<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\OAuth;

use App\Shared\Application\Clock;

/**
 * State OAuth anti-CSRF, SANS stockage serveur : tenant + fournisseur +
 * expiration, signés HMAC avec le secret applicatif. Le callback vérifie que
 * le state revient intact, non expiré, appartient bien AU TENANT connecté, et
 * en relit le fournisseur — le choix Gmail/Outlook voyage donc de manière
 * infalsifiable (pas un paramètre de requête libre).
 */
final class OAuthStateCodec
{
    private const int TTL_SECONDS = 600;

    public function __construct(
        private readonly string $appSecret,
        private readonly Clock $clock,
    ) {
    }

    public function issue(string $tenantId, string $provider): string
    {
        $expiresAt = $this->clock->now()->getTimestamp() + self::TTL_SECONDS;
        $payload = $tenantId.'|'.$provider.'|'.$expiresAt.'|'.bin2hex(random_bytes(8));
        $signature = hash_hmac('sha256', $payload, $this->appSecret);

        return rtrim(strtr(base64_encode($payload.'|'.$signature), '+/', '-_'), '=');
    }

    public function isValidFor(string $state, string $tenantId): bool
    {
        $parts = $this->verifiedParts($state);

        return null !== $parts && hash_equals($tenantId, $parts['tenant']);
    }

    /** Fournisseur porté par un state VALIDE (signé, non expiré) — null sinon. */
    public function providerFrom(string $state): ?string
    {
        return $this->verifiedParts($state)['provider'] ?? null;
    }

    /** @return array{tenant: string, provider: string}|null */
    private function verifiedParts(string $state): ?array
    {
        $decoded = base64_decode(strtr($state, '-_', '+/'), true);
        if (false === $decoded) {
            return null;
        }
        $parts = explode('|', $decoded);
        if (5 !== \count($parts)) {
            return null;
        }
        [$tenant, $provider, $expiresAt, $nonce, $signature] = $parts;
        $expected = hash_hmac('sha256', $tenant.'|'.$provider.'|'.$expiresAt.'|'.$nonce, $this->appSecret);
        if (!hash_equals($expected, $signature) || (int) $expiresAt < $this->clock->now()->getTimestamp()) {
            return null;
        }

        return ['tenant' => $tenant, 'provider' => $provider];
    }
}
