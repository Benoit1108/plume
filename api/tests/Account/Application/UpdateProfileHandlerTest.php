<?php

declare(strict_types=1);

namespace App\Tests\Account\Application;

use App\Account\Application\Command\UpdateProfile\UpdateProfile;
use App\Account\Application\Command\UpdateProfile\UpdateProfileHandler;
use App\Account\Domain\Profile\DigestFrequency;
use App\Account\Domain\Profile\Event\DigestFrequencyChanged;
use App\Account\Domain\Profile\Event\FollowUpCadenceChanged;
use App\Account\Domain\Profile\Event\NotificationPreferencesChanged;
use App\Account\Domain\Profile\Event\PipelineLabelsChanged;
use App\Account\Domain\Profile\Event\ProfileCreated;
use App\Account\Domain\Profile\Event\ProfileIdentityChanged;
use App\Account\Domain\Profile\Event\ProfilePresentationChanged;
use App\Account\Domain\Profile\Event\WeeklyGoalChanged;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\FixedClock;
use App\Tests\Support\InMemoryProfileRepository;
use App\Tests\Support\RecordingEventBus;
use PHPUnit\Framework\TestCase;

final class UpdateProfileHandlerTest extends TestCase
{
    private InMemoryProfileRepository $profiles;
    private RecordingEventBus $eventBus;
    private UpdateProfileHandler $handler;

    protected function setUp(): void
    {
        $this->profiles = new InMemoryProfileRepository();
        $this->eventBus = new RecordingEventBus();
        $this->handler = new UpdateProfileHandler(
            $this->profiles,
            $this->eventBus,
            new FixedClock(new \DateTimeImmutable('2026-07-13 10:00:00')),
        );
    }

    public function testCreatesProfileLazilyOnFirstChange(): void
    {
        ($this->handler)(new UpdateProfile('tenant-1', 8, 'Traductrice EN>FR.', null, 'Marie'));

        $profile = $this->profiles->find(TenantId::fromString('tenant-1'));
        self::assertSame(8, $profile?->weeklyGoal());
        self::assertSame('Europe/Paris', $profile->timezone());
        self::assertSame('Traductrice EN>FR.', $profile->bio());
        self::assertNull($profile->specialties());
        self::assertSame('Marie', $profile->signature());
        self::assertSame(1, $this->eventBus->countOf(ProfileCreated::class));
        self::assertSame(1, $this->eventBus->countOf(WeeklyGoalChanged::class));
        self::assertSame(1, $this->eventBus->countOf(ProfilePresentationChanged::class));
    }

    public function testChangesDigestFrequency(): void
    {
        // Défaut DAILY à la création → passer WEEKLY émet un event ; repasser WEEKLY est un no-op.
        ($this->handler)(new UpdateProfile('tenant-1', 5, null, null, null, null, null, 'WEEKLY'));
        self::assertSame(DigestFrequency::WEEKLY, $this->profiles->find(TenantId::fromString('tenant-1'))?->digestFrequency());
        self::assertSame(1, $this->eventBus->countOf(DigestFrequencyChanged::class));

        ($this->handler)(new UpdateProfile('tenant-1', 5, null, null, null, null, null, 'WEEKLY'));
        self::assertSame(1, $this->eventBus->countOf(DigestFrequencyChanged::class));
    }

    public function testChangesFollowUpCadence(): void
    {
        ($this->handler)(new UpdateProfile('tenant-1', 5, null, null, null, null, null, 'DAILY', [3, 14]));
        self::assertSame([3, 14], $this->profiles->find(TenantId::fromString('tenant-1'))?->followUpCadence());
        self::assertSame(1, $this->eventBus->countOf(FollowUpCadenceChanged::class));
    }

    public function testChangesPipelineLabels(): void
    {
        // Les libellés vides sont ignorés (retour au défaut) ; seuls les overrides non vides restent.
        ($this->handler)(new UpdateProfile('tenant-1', 5, null, null, null, null, null, 'DAILY', [7, 21, 45], ['WON' => 'Signée', 'LOST' => '   ']));
        self::assertSame(['WON' => 'Signée'], $this->profiles->find(TenantId::fromString('tenant-1'))?->pipelineLabels());
        self::assertSame(1, $this->eventBus->countOf(PipelineLabelsChanged::class));
    }

    public function testChangesNotificationPreferencesKeepingOnlyCuts(): void
    {
        // Défaut = tout activé : un type entièrement coché n'est PAS mémorisé ; seules les coupures restent.
        ($this->handler)(new UpdateProfile('tenant-1', 5, null, null, null, null, null, 'DAILY', [7, 21, 45], [], [
            'candidate_to_triage' => ['inApp' => true, 'email' => false],
            'reply_received' => ['inApp' => true, 'email' => true],
        ]));

        self::assertSame(
            ['candidate_to_triage' => ['inApp' => true, 'email' => false]],
            $this->profiles->find(TenantId::fromString('tenant-1'))?->notificationPreferences(),
        );
        self::assertSame(1, $this->eventBus->countOf(NotificationPreferencesChanged::class));

        // Re-soumettre à l'identique → no-op (aucun nouvel event).
        ($this->handler)(new UpdateProfile('tenant-1', 5, null, null, null, null, null, 'DAILY', [7, 21, 45], [], [
            'candidate_to_triage' => ['inApp' => true, 'email' => false],
        ]));
        self::assertSame(1, $this->eventBus->countOf(NotificationPreferencesChanged::class));
    }

    public function testUpdatesExistingProfileWithoutRecreating(): void
    {
        ($this->handler)(new UpdateProfile('tenant-1', 8, null, null, null));
        ($this->handler)(new UpdateProfile('tenant-1', 3, null, null, null));

        self::assertSame(3, $this->profiles->find(TenantId::fromString('tenant-1'))?->weeklyGoal());
        self::assertSame(1, $this->eventBus->countOf(ProfileCreated::class));
        self::assertSame(2, $this->eventBus->countOf(WeeklyGoalChanged::class));
    }

    public function testUnchangedPresentationEmitsNoEvent(): void
    {
        ($this->handler)(new UpdateProfile('tenant-1', 5, 'Bio.', 'Édition.', 'Marie'));
        ($this->handler)(new UpdateProfile('tenant-1', 5, '  Bio. ', 'Édition.', 'Marie'));

        self::assertSame(1, $this->eventBus->countOf(ProfilePresentationChanged::class));
    }

    public function testBlankPresentationIsStoredAsNull(): void
    {
        ($this->handler)(new UpdateProfile('tenant-1', 5, '   ', '', null));

        $profile = $this->profiles->find(TenantId::fromString('tenant-1'));
        self::assertNull($profile?->bio());
        self::assertNull($profile?->specialties());
    }

    public function testUpdatesDisplayIdentity(): void
    {
        ($this->handler)(new UpdateProfile('tenant-1', 5, null, null, null, 'Marie', 'Lefèvre'));

        $profile = $this->profiles->find(TenantId::fromString('tenant-1'));
        self::assertSame('Marie', $profile?->firstName());
        self::assertSame('Lefèvre', $profile->lastName());
        self::assertSame(1, $this->eventBus->countOf(ProfileIdentityChanged::class));

        // Rejouer à l'identique (aux espaces près) : aucun nouvel event.
        ($this->handler)(new UpdateProfile('tenant-1', 5, null, null, null, '  Marie ', 'Lefèvre'));
        self::assertSame(1, $this->eventBus->countOf(ProfileIdentityChanged::class));
    }

    public function testBlankIdentityIsStoredAsNull(): void
    {
        ($this->handler)(new UpdateProfile('tenant-1', 5, null, null, null, '   ', ''));

        $profile = $this->profiles->find(TenantId::fromString('tenant-1'));
        self::assertNull($profile?->firstName());
        self::assertNull($profile?->lastName());
        self::assertSame(0, $this->eventBus->countOf(ProfileIdentityChanged::class));
    }

    public function testRejectsOutOfRangeGoal(): void
    {
        $this->expectException(InvalidValue::class);
        ($this->handler)(new UpdateProfile('tenant-1', 0, null, null, null));
    }
}
