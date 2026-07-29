<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Security;

use OTPHP\TOTP;

/**
 * TOTP (RFC 6238) via spomky-labs/otphp — lib de référence (Base32 constant-time Paragonie),
 * choisie plutôt qu'une implémentation maison : les bugs de TOTP artisanal sont subtils
 * (comparaison, fenêtre, padding). Wrapper fin : le reste du code ne connaît pas la lib.
 * Période 30 s, 6 chiffres, SHA-1 (compat universelle des apps d'authentification).
 */
final class TotpService
{
    public const int PERIOD = 30;

    /** Fenêtre de tolérance : le pas courant ± 1 (dérive d'horloge du téléphone). */
    private const int WINDOW = 1;

    public function generateSecret(): string
    {
        return TOTP::generate()->getSecret();
    }

    public function provisioningUri(string $secret, string $email): string
    {
        $totp = TOTP::createFromSecret(self::nonEmpty($secret));
        $totp->setLabel(self::nonEmpty('' !== $email ? $email : 'compte'));
        $totp->setIssuer('Plume');

        return $totp->getProvisioningUri();
    }

    /**
     * Vérifie un code et renvoie le PAS DE TEMPS auquel il correspond (pour l'anti-rejeu),
     * ou null si invalide. On teste chaque pas de la fenêtre individuellement (leeway 0)
     * précisément pour savoir LEQUEL a matché.
     */
    public function verify(string $secret, string $code, ?int $now = null): ?int
    {
        if (1 !== preg_match('/^\d{6}$/', $code)) {
            return null;
        }
        $now ??= time();
        $totp = TOTP::createFromSecret(self::nonEmpty($secret));

        foreach ([0, -1, 1] as $offset) {
            $timestamp = $now + ($offset * self::PERIOD);
            if ($timestamp >= 0 && $totp->verify($code, $timestamp, 0)) {
                return intdiv($timestamp, self::PERIOD);
            }
        }

        return null;
    }

    /** Code TOTP courant pour un secret (tests + outillage). */
    public function currentCode(string $secret, ?int $now = null): string
    {
        return TOTP::createFromSecret(self::nonEmpty($secret))->at(max(0, $now ?? time()));
    }

    /** @return non-empty-string */
    private static function nonEmpty(string $value): string
    {
        if ('' === $value) {
            throw new \InvalidArgumentException('TOTP secret/label cannot be empty.');
        }

        return $value;
    }

    /**
     * Codes de secours : 8 codes lisibles `xxxx-xxxx` (hex), retournés EN CLAIR une seule fois ;
     * seuls les hashs sha256 sont stockés.
     *
     * @return array{plain: list<string>, hashed: list<string>}
     */
    public function generateBackupCodes(): array
    {
        $plain = [];
        $hashed = [];
        for ($i = 0; $i < 8; ++$i) {
            // 64 bits d'entropie (revue globale) : anti-brute-force offline sur un dump hashé.
            $code = bin2hex(random_bytes(4)).'-'.bin2hex(random_bytes(4));
            $plain[] = $code;
            $hashed[] = self::hashBackupCode($code);
        }

        return ['plain' => $plain, 'hashed' => $hashed];
    }

    public static function hashBackupCode(string $code): string
    {
        return hash('sha256', strtolower(trim($code)));
    }

    /** Vérifie que la fenêtre couvre bien ± WINDOW pas (documentation vivante de la constante). */
    public function window(): int
    {
        return self::WINDOW;
    }
}
