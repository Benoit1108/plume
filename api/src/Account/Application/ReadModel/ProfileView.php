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
    ) {
    }
}
