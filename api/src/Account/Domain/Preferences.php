<?php

declare(strict_types=1);

namespace App\Account\Domain;

/**
 * Préférences de l'utilisatrice (value object) : locale UI, thème, fuseau horaire.
 * NB : la locale UI (langue de l'interface) est distincte des paires de langues
 * de traduction et de la langue cible du contenu généré (cf. ADR-0011).
 */
final class Preferences
{
    public const array SUPPORTED_LOCALES = ['fr', 'en'];

    public function __construct(
        public readonly string $locale = 'fr',
        public readonly Theme $theme = Theme::SYSTEM,
        public readonly string $timezone = 'Europe/Paris',
    ) {
        if (!\in_array($locale, self::SUPPORTED_LOCALES, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported UI locale "%s".', $locale));
        }

        if (!\in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            throw new \InvalidArgumentException(sprintf('Unknown timezone "%s".', $timezone));
        }
    }
}
