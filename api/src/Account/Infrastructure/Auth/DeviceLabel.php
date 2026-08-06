<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Auth;

/**
 * Résumé lisible d'un User-Agent : navigateur + plateforme. Sert à rendre les sessions actives
 * IDENTIFIABLES (« Firefox · Linux ») — sans quoi « révoquez ce que vous ne reconnaissez pas »
 * est impossible à suivre.
 *
 * Volontairement grossier : on ne veut pas empreinter l'appareil, juste permettre à l'utilisatrice
 * de reconnaître le sien. Inconnu → `null` (le front affiche un libellé de repli traduit).
 */
final readonly class DeviceLabel
{
    private function __construct(
        public ?string $browser,
        public ?string $platform,
    ) {
    }

    public static function fromUserAgent(?string $userAgent): self
    {
        $ua = trim($userAgent ?? '');
        if ('' === $ua) {
            return new self(null, null);
        }

        return new self(self::browser($ua), self::platform($ua));
    }

    /**
     * L'ordre est significatif : Edge et Opera se déclarent aussi « Chrome », et Chrome se
     * déclare « Safari ». Le premier marqueur trouvé gagne.
     */
    private static function browser(string $ua): ?string
    {
        $markers = [
            'Edg/' => 'Edge',
            'OPR/' => 'Opera',
            'Firefox/' => 'Firefox',
            'CriOS/' => 'Chrome',  // Chrome sur iOS
            'Chrome/' => 'Chrome',
            'Safari/' => 'Safari',
        ];

        return self::firstMatch($ua, $markers);
    }

    /** Même logique : iOS/Android contiennent « Mac OS X »/« Linux », donc ils passent d'abord. */
    private static function platform(string $ua): ?string
    {
        $markers = [
            'iPhone' => 'iPhone',
            'iPad' => 'iPad',
            'Android' => 'Android',
            'Windows' => 'Windows',
            'CrOS' => 'ChromeOS',
            'Mac OS X' => 'macOS',
            'Linux' => 'Linux',
        ];

        return self::firstMatch($ua, $markers);
    }

    /** @param array<string, string> $markers */
    private static function firstMatch(string $ua, array $markers): ?string
    {
        foreach ($markers as $needle => $label) {
            if (str_contains($ua, $needle)) {
                return $label;
            }
        }

        return null;
    }
}
