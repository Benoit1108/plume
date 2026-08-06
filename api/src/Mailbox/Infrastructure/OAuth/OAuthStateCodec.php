<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\OAuth;

use App\Shared\Application\Clock;
use Psr\Cache\CacheItemPoolInterface;

/**
 * State OAuth anti-CSRF : tenant + fournisseur + expiration, signés HMAC avec le secret applicatif.
 * Le callback vérifie que le state revient intact, non expiré, appartient bien AU TENANT connecté,
 * et en relit le fournisseur — le choix Gmail/Outlook voyage donc de manière infalsifiable (pas un
 * paramètre de requête libre).
 *
 * La signature seule laissait le state REJOUABLE pendant ses 10 minutes (revue P3) : `consume()`
 * marque le nonce comme utilisé, le temps de sa validité résiduelle. Le stockage se limite à ça —
 * l'émission reste sans état.
 */
final class OAuthStateCodec
{
    private const int TTL_SECONDS = 600;

    public function __construct(
        private readonly string $appSecret,
        private readonly Clock $clock,
        private readonly CacheItemPoolInterface $usedStates,
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

        return null !== $parts && hash_equals($tenantId, $parts['tenant']) && !$this->alreadyUsed($parts['nonce']);
    }

    /**
     * Valide le state ET le brûle : un même state ne peut servir qu'une fois. À appeler par le
     * callback ; retourne faux si le state est invalide, expiré, d'un autre tenant, ou déjà consommé.
     */
    public function consume(string $state, string $tenantId): bool
    {
        $parts = $this->verifiedParts($state);
        if (null === $parts || !hash_equals($tenantId, $parts['tenant']) || $this->alreadyUsed($parts['nonce'])) {
            return false;
        }

        $item = $this->usedStates->getItem(self::cacheKey($parts['nonce']));
        $item->set(true);
        // Au-delà de l'expiration du state, la signature suffit à le refuser : rien à mémoriser.
        $item->expiresAfter(max(1, $parts['expiresAt'] - $this->clock->now()->getTimestamp()));
        $this->usedStates->save($item);

        return true;
    }

    private function alreadyUsed(string $nonce): bool
    {
        return $this->usedStates->getItem(self::cacheKey($nonce))->isHit();
    }

    private static function cacheKey(string $nonce): string
    {
        return 'oauth_state_used_'.$nonce;
    }

    /** Fournisseur porté par un state VALIDE (signé, non expiré) — null sinon. */
    public function providerFrom(string $state): ?string
    {
        return $this->verifiedParts($state)['provider'] ?? null;
    }

    /** @return array{tenant: string, provider: string, nonce: string, expiresAt: int}|null */
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

        return ['tenant' => $tenant, 'provider' => $provider, 'nonce' => $nonce, 'expiresAt' => (int) $expiresAt];
    }
}
