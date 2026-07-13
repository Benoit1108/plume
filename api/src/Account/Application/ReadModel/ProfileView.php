<?php

declare(strict_types=1);

namespace App\Account\Application\ReadModel;

/** Vue du profil (défauts appliqués si jamais personnalisé). */
final class ProfileView
{
    public function __construct(
        public readonly int $weeklyGoal,
        public readonly string $timezone,
    ) {
    }
}
