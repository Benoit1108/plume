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
    ) {
    }
}
