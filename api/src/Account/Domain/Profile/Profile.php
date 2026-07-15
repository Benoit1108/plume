<?php

declare(strict_types=1);

namespace App\Account\Domain\Profile;

use App\Account\Domain\Profile\Event\ProfileCreated;
use App\Account\Domain\Profile\Event\ProfileIdentityChanged;
use App\Account\Domain\Profile\Event\ProfilePresentationChanged;
use App\Account\Domain\Profile\Event\WeeklyGoalChanged;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Profil de la traductrice — agrégat du contexte Account (un par tenant).
 * M1.3 : objectif hebdomadaire + fuseau. M1.4 : présentation (bio, spécialités,
 * signature) — la matière première des prompts de génération.
 */
final class Profile extends AggregateRoot
{
    public const int DEFAULT_WEEKLY_GOAL = 5;
    public const string DEFAULT_TIMEZONE = 'Europe/Paris';

    private function __construct(
        private readonly TenantId $tenantId,
        private int $weeklyGoal,
        private string $timezone,
        private ?string $bio = null,
        private ?string $specialties = null,
        private ?string $signature = null,
        private ?string $firstName = null,
        private ?string $lastName = null,
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

    /** Présentation (bio, spécialités, signature) — sans changement, aucun event. */
    public function changePresentation(?string $bio, ?string $specialties, ?string $signature, \DateTimeImmutable $now): void
    {
        $bio = $this->normalize($bio);
        $specialties = $this->normalize($specialties);
        $signature = $this->normalize($signature);

        if ($bio === $this->bio && $specialties === $this->specialties && $signature === $this->signature) {
            return;
        }

        $this->bio = $bio;
        $this->specialties = $specialties;
        $this->signature = $signature;
        $this->recordEvent(new ProfilePresentationChanged($this->tenantId->toString(), $now));
    }

    /** Identité affichée (nom/prénom) — sans changement, aucun event. */
    public function changeIdentity(?string $firstName, ?string $lastName, \DateTimeImmutable $now): void
    {
        $firstName = $this->normalize($firstName);
        $lastName = $this->normalize($lastName);

        if ($firstName === $this->firstName && $lastName === $this->lastName) {
            return;
        }

        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->recordEvent(new ProfileIdentityChanged($this->tenantId->toString(), $now));
    }

    private function normalize(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
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

    public function bio(): ?string
    {
        return $this->bio;
    }

    public function specialties(): ?string
    {
        return $this->specialties;
    }

    public function signature(): ?string
    {
        return $this->signature;
    }

    public function firstName(): ?string
    {
        return $this->firstName;
    }

    public function lastName(): ?string
    {
        return $this->lastName;
    }
}
