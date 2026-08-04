<?php

declare(strict_types=1);

namespace App\Account\Application\Command\UpdateProfile;

use App\Shared\Application\Command\Command;

/** Réglages du profil : objectif hebdo + présentation (bio, spécialités, signature). */
final class UpdateProfile implements Command
{
    public function __construct(
        public readonly string $tenantId,
        public readonly int $weeklyGoal,
        public readonly ?string $bio,
        public readonly ?string $specialties,
        public readonly ?string $signature,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly string $digestFrequency = 'DAILY',
        /** @var int[] séquence de relance (délais en jours) */
        public readonly array $followUpCadence = [7, 21, 45],
        /** @var array<string, string> overrides de libellés d'étapes du pipeline */
        public readonly array $pipelineLabels = [],
        /** @var array<array-key, mixed> coupures de notification par type (entrée non fiable) */
        public readonly array $notificationPreferences = [],
        /** Seuil de dormance des clients gagnés en jours (0 = désactivé). */
        public readonly int $dormantClientThresholdDays = 120,
        /** Bilan hebdomadaire par email (opt-out). */
        public readonly bool $weeklyReportEnabled = true,
    ) {
    }
}
