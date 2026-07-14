<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\ReadModel;

use App\Account\Application\ReadModel\ProfileSettings;
use App\Account\Application\ReadModel\ProfileView;
use App\Account\Domain\Profile\Profile;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Doctrine\DBAL\Connection;

/** Lecture du profil courant (SQL direct, FAIL-CLOSED tenant, défauts si absent). */
final class DoctrineProfileSettings implements ProfileSettings
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function current(): ProfileView
    {
        $tenant = $this->tenantContext->get()
            ?? throw new \LogicException('Profile settings queried without tenant in context — refusing to run an unscoped query.');

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
            $this->text($row, 'bio'),
            $this->text($row, 'specialties'),
            $this->text($row, 'signature'),
        );
    }

    /** @param array<string, mixed> $row */
    private function text(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
