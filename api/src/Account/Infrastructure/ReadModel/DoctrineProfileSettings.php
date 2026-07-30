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
            'SELECT weekly_goal, timezone, bio, specialties, signature, first_name, last_name, digest_frequency, follow_up_cadence, pipeline_labels FROM profile WHERE tenant_id = :tenant',
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
            $this->strOrNull($row, 'first_name'),
            $this->strOrNull($row, 'last_name'),
            \is_string($row['digest_frequency'] ?? null) && '' !== $row['digest_frequency'] ? $row['digest_frequency'] : 'DAILY',
            $this->cadenceOf($row['follow_up_cadence'] ?? null),
            $this->labelsOf($row['pipeline_labels'] ?? null),
        );
    }

    /** @return array<string, string> */
    private function labelsOf(mixed $raw): array
    {
        if (!\is_string($raw) || '' === $raw) {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!\is_array($decoded)) {
            return [];
        }

        $labels = [];
        foreach ($decoded as $status => $label) {
            if (\is_string($label) && '' !== $label) {
                $labels[(string) $status] = $label;
            }
        }

        return $labels;
    }

    /** @return int[] */
    private function cadenceOf(mixed $raw): array
    {
        if (!\is_string($raw) || '' === $raw) {
            return Profile::DEFAULT_FOLLOW_UP_CADENCE;
        }
        $decoded = json_decode($raw, true);
        if (!\is_array($decoded)) {
            return Profile::DEFAULT_FOLLOW_UP_CADENCE;
        }

        return array_values(array_filter(array_map(
            static fn (mixed $d): ?int => is_numeric($d) ? (int) $d : null,
            $decoded,
        ), static fn (?int $d): bool => null !== $d));
    }
}
