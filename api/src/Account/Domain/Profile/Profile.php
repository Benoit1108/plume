<?php

declare(strict_types=1);

namespace App\Account\Domain\Profile;

use App\Account\Domain\Profile\Event\DigestFrequencyChanged;
use App\Account\Domain\Profile\Event\DormantClientThresholdChanged;
use App\Account\Domain\Profile\Event\FollowUpCadenceChanged;
use App\Account\Domain\Profile\Event\NotificationPreferencesChanged;
use App\Account\Domain\Profile\Event\PipelineLabelsChanged;
use App\Account\Domain\Profile\Event\ProfileCreated;
use App\Account\Domain\Profile\Event\ProfileIdentityChanged;
use App\Account\Domain\Profile\Event\ProfilePresentationChanged;
use App\Account\Domain\Profile\Event\WeeklyGoalChanged;
use App\Account\Domain\Profile\Event\WeeklyReportPreferenceChanged;
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
    /** @var int[] séquence de relance par défaut (miroir de FollowUpCadence côté Prospecting) */
    public const array DEFAULT_FOLLOW_UP_CADENCE = [7, 21, 45];
    /** Seuil de dormance d'un client GAGNÉ (jours sans interaction) avant proposition de réactivation. */
    public const int DEFAULT_DORMANT_THRESHOLD_DAYS = 120;
    /** 0 = réactivation désactivée ; borne haute pragmatique (2 ans). */
    private const int DORMANT_MAX_DAYS = 730;
    /** Bilan hebdomadaire par email activé par défaut (opt-out dans les Réglages). */
    public const bool DEFAULT_WEEKLY_REPORT_ENABLED = true;

    private const int CADENCE_MAX_STEPS = 10;
    private const int CADENCE_MAX_DAYS = 365;

    /** @param int[] $followUpCadence */
    private function __construct(
        private readonly TenantId $tenantId,
        private int $weeklyGoal,
        private string $timezone,
        private ?string $bio = null,
        private ?string $specialties = null,
        private ?string $signature = null,
        private ?string $firstName = null,
        private ?string $lastName = null,
        private DigestFrequency $digestFrequency = DigestFrequency::DAILY,
        private array $followUpCadence = self::DEFAULT_FOLLOW_UP_CADENCE,
        /** @var array<string, string> overrides de libellés d'étapes du pipeline (statut → libellé) */
        private array $pipelineLabels = [],
        /** @var array<string, array{inApp: bool, email: bool}> COUPURES par type (défaut = tout activé) */
        private array $notificationPreferences = [],
        private int $dormantClientThresholdDays = self::DEFAULT_DORMANT_THRESHOLD_DAYS,
        private bool $weeklyReportEnabled = self::DEFAULT_WEEKLY_REPORT_ENABLED,
    ) {
    }

    public static function create(TenantId $tenantId, \DateTimeImmutable $now): self
    {
        $profile = new self($tenantId, self::DEFAULT_WEEKLY_GOAL, self::DEFAULT_TIMEZONE);
        $profile->recordEvent(new ProfileCreated($tenantId->toString(), $now));

        return $profile;
    }

    /** Fréquence du digest email — sans changement, aucun event. */
    public function changeDigestFrequency(DigestFrequency $frequency, \DateTimeImmutable $now): void
    {
        if ($frequency === $this->digestFrequency) {
            return;
        }

        $this->digestFrequency = $frequency;
        $this->recordEvent(new DigestFrequencyChanged($this->tenantId->toString(), $frequency->value, $now));
    }

    /**
     * Séquence de relance (délais en jours entre étapes). Vide = aucune relance auto.
     * Sans changement, aucun event.
     *
     * @param int[] $days
     */
    public function changeFollowUpCadence(array $days, \DateTimeImmutable $now): void
    {
        $days = array_values($days);
        if (\count($days) > self::CADENCE_MAX_STEPS) {
            throw InvalidValue::because(\sprintf('A follow-up cadence has at most %d steps.', self::CADENCE_MAX_STEPS));
        }
        foreach ($days as $delay) {
            if ($delay < 1 || $delay > self::CADENCE_MAX_DAYS) {
                throw InvalidValue::because(\sprintf('Each follow-up delay must be between 1 and %d days.', self::CADENCE_MAX_DAYS));
            }
        }

        if ($days === $this->followUpCadence) {
            return;
        }

        $this->followUpCadence = $days;
        $this->recordEvent(new FollowUpCadenceChanged($this->tenantId->toString(), $days, $now));
    }

    /**
     * Libellés d'étapes du pipeline personnalisés (statut → libellé). PUREMENT COSMÉTIQUE (ADR-0031) :
     * la machine à états ne change pas. On ne garde que des overrides non vides (≤ 40 car.), 20 max ;
     * vider un libellé = retour au défaut. Sans changement, aucun event.
     *
     * @param array<string, string> $labels
     */
    public function changePipelineLabels(array $labels, \DateTimeImmutable $now): void
    {
        $clean = [];
        foreach ($labels as $status => $label) {
            $trimmed = trim($label);
            if ('' !== $trimmed) {
                $clean[(string) $status] = mb_substr($trimmed, 0, 40);
            }
        }
        if (\count($clean) > 20) {
            throw InvalidValue::because('At most 20 pipeline labels can be customised.');
        }

        if ($clean === $this->pipelineLabels) {
            return;
        }

        $this->pipelineLabels = $clean;
        $this->recordEvent(new PipelineLabelsChanged($this->tenantId->toString(), $now));
    }

    /**
     * Préférences fines de notification par TYPE et par CANAL (in-app / email). Le défaut est « tout
     * activé » : on ne conserve donc QUE les coupures (au moins un canal à false) — un profil sans
     * override reçoit tout. Pas de couplage aux types du contexte Notification (clé libre, bornée).
     *
     * @param array<array-key, mixed> $preferences type → canaux (entrée non fiable, normalisée ici)
     */
    public function changeNotificationPreferences(array $preferences, \DateTimeImmutable $now): void
    {
        $clean = [];
        foreach ($preferences as $type => $channels) {
            if (!\is_string($type) || '' === $type || !\is_array($channels)) {
                continue;
            }
            $inApp = (bool) ($channels['inApp'] ?? true);
            $email = (bool) ($channels['email'] ?? true);
            if (!$inApp || !$email) { // seules les coupures sont mémorisées (défaut = activé)
                $clean[mb_substr($type, 0, 50)] = ['inApp' => $inApp, 'email' => $email];
            }
        }
        if (\count($clean) > 20) {
            throw InvalidValue::because('At most 20 notification preferences can be customised.');
        }

        if ($clean === $this->notificationPreferences) {
            return;
        }

        $this->notificationPreferences = $clean;
        $this->recordEvent(new NotificationPreferencesChanged($this->tenantId->toString(), $now));
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

    /** Seuil de dormance des clients gagnés (0 = réactivation désactivée). Sans changement, aucun event. */
    public function changeDormantClientThreshold(int $days, \DateTimeImmutable $now): void
    {
        if ($days < 0 || $days > self::DORMANT_MAX_DAYS) {
            throw InvalidValue::because(sprintf('Dormant threshold must be between 0 and %d days.', self::DORMANT_MAX_DAYS));
        }
        if ($days === $this->dormantClientThresholdDays) {
            return;
        }

        $this->dormantClientThresholdDays = $days;
        $this->recordEvent(new DormantClientThresholdChanged($this->tenantId->toString(), $days, $now));
    }

    /** Bilan hebdomadaire par email (opt-out). Sans changement, aucun event. */
    public function changeWeeklyReportEnabled(bool $enabled, \DateTimeImmutable $now): void
    {
        if ($enabled === $this->weeklyReportEnabled) {
            return;
        }

        $this->weeklyReportEnabled = $enabled;
        $this->recordEvent(new WeeklyReportPreferenceChanged($this->tenantId->toString(), $enabled, $now));
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

    public function dormantClientThresholdDays(): int
    {
        return $this->dormantClientThresholdDays;
    }

    public function weeklyReportEnabled(): bool
    {
        return $this->weeklyReportEnabled;
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

    public function digestFrequency(): DigestFrequency
    {
        return $this->digestFrequency;
    }

    /** @return int[] */
    public function followUpCadence(): array
    {
        return $this->followUpCadence;
    }

    /** @return array<string, string> */
    public function pipelineLabels(): array
    {
        return $this->pipelineLabels;
    }

    /** @return array<string, array{inApp: bool, email: bool}> coupures par type (défaut = activé) */
    public function notificationPreferences(): array
    {
        return $this->notificationPreferences;
    }
}
