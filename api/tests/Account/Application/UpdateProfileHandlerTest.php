<?php

declare(strict_types=1);

namespace App\Tests\Account\Application;

use App\Account\Application\Command\UpdateProfile\UpdateProfile;
use App\Account\Application\Command\UpdateProfile\UpdateProfileHandler;
use App\Account\Domain\Profile\Event\ProfileCreated;
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

    public function testRejectsOutOfRangeGoal(): void
    {
        $this->expectException(InvalidValue::class);
        ($this->handler)(new UpdateProfile('tenant-1', 0, null, null, null));
    }
}
