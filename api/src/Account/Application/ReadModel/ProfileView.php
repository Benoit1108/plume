<?php

declare(strict_types=1);

namespace App\Account\Application\ReadModel;

/** Vue du profil (défauts appliqués si jamais personnalisé). */
final class ProfileView
{
    public function __construct(
        public readonly int $weeklyGoal,
        public readonly string $timezone,
        public readonly ?string $bio = null,
        public readonly ?string $specialties = null,
        public readonly ?string $signature = null,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly string $digestFrequency = 'DAILY',
        /** @var int[] séquence de relance (délais en jours entre étapes) */
        public readonly array $followUpCadence = [7, 21, 45],
        /** @var array<string, string> overrides de libellés d'étapes du pipeline */
        public readonly array $pipelineLabels = [],
        /** @var array<string, array{inApp: bool, email: bool}> coupures de notification par type */
        public readonly array $notificationPreferences = [],
        /** Seuil de dormance des clients gagnés en jours (0 = réactivation désactivée). */
        public readonly int $dormantClientThresholdDays = 120,
        /** Bilan hebdomadaire par email activé (opt-out). */
        public readonly bool $weeklyReportEnabled = true,
    ) {
    }
}
