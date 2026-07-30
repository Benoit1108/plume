<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\Gateway;

use App\Prospecting\Application\FollowUpCadenceProvider;
use App\Prospecting\Domain\Lead\FollowUpCadence;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Doctrine\DBAL\Connection;

/**
 * Lit la séquence de relance dans `profile.follow_up_cadence` (JSON `int[]`) du tenant courant —
 * même patron que le tick de relances qui lit `profile.timezone` directement. Lecture TOLÉRANTE
 * (via {@see FollowUpCadence::fromStoredDays}) : profil absent, colonne nulle ou JSON abîmé →
 * cadence par défaut, la planification ne casse jamais.
 */
final class ProfileFollowUpCadenceProvider implements FollowUpCadenceProvider
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function forCurrentTenant(): FollowUpCadence
    {
        $raw = $this->connection->fetchOne(
            'SELECT follow_up_cadence FROM profile WHERE tenant_id = :tenant',
            ['tenant' => $this->tenantContext->require()->toString()],
        );

        if (!\is_string($raw) || '' === $raw) {
            return FollowUpCadence::default();
        }

        $decoded = json_decode($raw, true);
        if (!\is_array($decoded)) {
            return FollowUpCadence::default();
        }

        $days = array_values(array_filter(array_map(
            static fn (mixed $d): ?int => is_numeric($d) ? (int) $d : null,
            $decoded,
        ), static fn (?int $d): bool => null !== $d));

        return FollowUpCadence::fromStoredDays($days);
    }
}
