<?php

declare(strict_types=1);

namespace App\Account\Domain\Profile;

use App\Shared\Domain\ValueObject\TenantId;

interface ProfileRepository
{
    public function save(Profile $profile): void;

    /** Null si le profil n'a jamais été personnalisé (défauts appliqués en lecture). */
    public function find(TenantId $tenantId): ?Profile;
}
