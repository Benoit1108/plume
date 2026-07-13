<?php

declare(strict_types=1);

namespace App\Tests\Account\Application;

use App\Account\Application\Command\UpdateWeeklyGoal\UpdateWeeklyGoal;
use App\Account\Application\Command\UpdateWeeklyGoal\UpdateWeeklyGoalHandler;
use App\Account\Domain\Profile\Event\ProfileCreated;
use App\Account\Domain\Profile\Event\WeeklyGoalChanged;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\FixedClock;
use App\Tests\Support\InMemoryProfileRepository;
use App\Tests\Support\RecordingEventBus;
use PHPUnit\Framework\TestCase;

final class UpdateWeeklyGoalHandlerTest extends TestCase
{
    private InMemoryProfileRepository $profiles;
    private RecordingEventBus $eventBus;
    private UpdateWeeklyGoalHandler $handler;

    protected function setUp(): void
    {
        $this->profiles = new InMemoryProfileRepository();
        $this->eventBus = new RecordingEventBus();
        $this->handler = new UpdateWeeklyGoalHandler(
            $this->profiles,
            $this->eventBus,
            new FixedClock(new \DateTimeImmutable('2026-07-13 10:00:00')),
        );
    }

    public function testCreatesProfileLazilyOnFirstChange(): void
    {
        ($this->handler)(new UpdateWeeklyGoal('tenant-1', 8));

        $profile = $this->profiles->find(TenantId::fromString('tenant-1'));
        self::assertSame(8, $profile?->weeklyGoal());
        self::assertSame('Europe/Paris', $profile->timezone());
        self::assertSame(1, $this->eventBus->countOf(ProfileCreated::class));
        self::assertSame(1, $this->eventBus->countOf(WeeklyGoalChanged::class));
    }

    public function testUpdatesExistingProfileWithoutRecreating(): void
    {
        ($this->handler)(new UpdateWeeklyGoal('tenant-1', 8));
        ($this->handler)(new UpdateWeeklyGoal('tenant-1', 3));

        self::assertSame(3, $this->profiles->find(TenantId::fromString('tenant-1'))?->weeklyGoal());
        self::assertSame(1, $this->eventBus->countOf(ProfileCreated::class));
        self::assertSame(2, $this->eventBus->countOf(WeeklyGoalChanged::class));
    }

    public function testRejectsOutOfRangeGoal(): void
    {
        $this->expectException(InvalidValue::class);
        ($this->handler)(new UpdateWeeklyGoal('tenant-1', 0));
    }
}
