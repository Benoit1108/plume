<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Account\Domain\Profile\Profile;
use App\Account\Domain\Profile\ProfileRepository;
use App\Shared\Domain\ValueObject\TenantId;

final class InMemoryProfileRepository implements ProfileRepository
{
    /** @var array<string, Profile> */
    private array $profiles = [];

    public function save(Profile $profile): void
    {
        $this->profiles[$profile->tenantId()->toString()] = $profile;
    }

    public function find(TenantId $tenantId): ?Profile
    {
        return $this->profiles[$tenantId->toString()] ?? null;
    }
}
