<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\ReadModel;

use App\Account\Application\ReadModel\ProfileSettings;
use App\Account\Application\ReadModel\ProfileView;
use App\Account\Domain\Profile\Profile;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use App\Shared\Infrastructure\Persistence\Doctrine\HydratesRows;
use Doctrine\DBAL\Connection;

/** Lecture du profil courant (SQL direct, FAIL-CLOSED tenant, défauts si absent). */
final class DoctrineProfileSettings implements ProfileSettings
{
    use HydratesRows;

    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function current(): ProfileView
    {
        $tenant = $this->tenantContext->require();

        $row = $this->connection->fetchAssociative(
            'SELECT weekly_goal, timezone, bio, specialties, signature FROM profile WHERE tenant_id = :tenant',
            ['tenant' => $tenant->toString()],
        );

        if (false === $row) {
            return new ProfileView(Profile::DEFAULT_WEEKLY_GOAL, Profile::DEFAULT_TIMEZONE);
        }

        return new ProfileView(
            is_numeric($row['weekly_goal'] ?? null) ? (int) $row['weekly_goal'] : Profile::DEFAULT_WEEKLY_GOAL,
            \is_string($row['timezone'] ?? null) && '' !== $row['timezone'] ? $row['timezone'] : Profile::DEFAULT_TIMEZONE,
            $this->strOrNull($row, 'bio'),
            $this->strOrNull($row, 'specialties'),
            $this->strOrNull($row, 'signature'),
        );
    }
}
