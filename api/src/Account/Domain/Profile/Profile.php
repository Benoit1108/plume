<?php

declare(strict_types=1);

namespace App\Account\Domain\Profile;

use App\Account\Domain\Profile\Event\ProfileCreated;
use App\Account\Domain\Profile\Event\WeeklyGoalChanged;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Profil de la traductrice — agrégat du contexte Account (un par tenant).
 * M1.3 : objectif hebdomadaire + fuseau. Le profil métier complet
 * (bio, langues, tarifs, signature) arrive en M1.4 pour la génération.
 */
final class Profile extends AggregateRoot
{
    public const int DEFAULT_WEEKLY_GOAL = 5;
    public const string DEFAULT_TIMEZONE = 'Europe/Paris';

    private function __construct(
        private readonly TenantId $tenantId,
        private int $weeklyGoal,
        private string $timezone,
    ) {
    }

    public static function create(TenantId $tenantId, \DateTimeImmutable $now): self
    {
        $profile = new self($tenantId, self::DEFAULT_WEEKLY_GOAL, self::DEFAULT_TIMEZONE);
        $profile->recordEvent(new ProfileCreated($tenantId->toString(), $now));

        return $profile;
    }

    public function changeWeeklyGoal(int $weeklyGoal, \DateTimeImmutable $now): void
    {
        if ($weeklyGoal < 1 || $weeklyGoal > 99) {
            throw InvalidValue::because('Weekly goal must be between 1 and 99.');
        }
        if ($weeklyGoal === $this->weeklyGoal) {
            return;
        }

        $this->weeklyGoal = $weeklyGoal;
        $this->recordEvent(new WeeklyGoalChanged($this->tenantId->toString(), $weeklyGoal, $now));
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function weeklyGoal(): int
    {
        return $this->weeklyGoal;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }
}
