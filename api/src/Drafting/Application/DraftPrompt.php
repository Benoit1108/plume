<?php

declare(strict_types=1);

namespace App\Drafting\Application;

/**
 * Matière première d'une génération : profil + cible + piste + gabarit éventuel.
 * La langue CIBLE est celle du prospect (ADR-0011), pas celle de l'UI.
 */
final class DraftPrompt
{
    public function __construct(
        public readonly string $type,
        public readonly string $targetLanguage,
        public readonly string $languagePair,
        public readonly string $leadStatus,
        public readonly string $organizationName,
        public readonly string $segment,
        public readonly ?string $contactName,
        public readonly ?string $bio,
        public readonly ?string $specialties,
        public readonly ?string $signature,
        public readonly ?string $templateSubject,
        public readonly ?string $templateBody,
    ) {
    }
}
