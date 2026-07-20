<?php

declare(strict_types=1);

namespace App\Tests\Sourcing\Application;

use App\Sourcing\Application\Command\AddAlertFeed\AddAlertFeed;
use App\Sourcing\Application\Command\AddAlertFeed\AddAlertFeedHandler;
use App\Sourcing\Application\Command\RemoveAlertFeed\RemoveAlertFeed;
use App\Sourcing\Application\Command\RemoveAlertFeed\RemoveAlertFeedHandler;
use App\Sourcing\Application\Command\SetAlertFeedActive\SetAlertFeedActive;
use App\Sourcing\Application\Command\SetAlertFeedActive\SetAlertFeedActiveHandler;
use App\Sourcing\Domain\AlertFeed\AlertFeedId;
use App\Sourcing\Domain\AlertFeed\Exception\AlertFeedLimitReached;
use App\Sourcing\Domain\AlertFeed\Exception\AlertFeedNotFound;
use App\Tests\Support\FixedClock;
use App\Tests\Support\InMemoryAlertFeedRepository;
use App\Tests\Support\RecordingEventBus;
use App\Tests\Support\SequentialIdGenerator;
use PHPUnit\Framework\TestCase;

final class AlertFeedHandlersTest extends TestCase
{
    private InMemoryAlertFeedRepository $feeds;
    private FixedClock $clock;
    private RecordingEventBus $eventBus;

    protected function setUp(): void
    {
        $this->feeds = new InMemoryAlertFeedRepository();
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-07-20 10:00:00'));
        $this->eventBus = new RecordingEventBus();
    }

    public function testAddCreatesFeed(): void
    {
        $handler = new AddAlertFeedHandler($this->feeds, new SequentialIdGenerator(), $this->clock, $this->eventBus);

        ($handler)(new AddAlertFeed('tenant-1', 'RSS', 'https://proz.example/rss', 'ProZ'));

        self::assertSame(1, $this->feeds->count());
    }

    public function testEnforcesTheFeedLimitPerTenant(): void
    {
        $handler = new AddAlertFeedHandler($this->feeds, new SequentialIdGenerator(), $this->clock, $this->eventBus);
        for ($i = 0; $i < 25; ++$i) {
            ($handler)(new AddAlertFeed('tenant-1', 'RSS', 'https://f'.$i.'.example/rss', null));
        }

        $this->expectException(AlertFeedLimitReached::class);
        ($handler)(new AddAlertFeed('tenant-1', 'RSS', 'https://over.example/rss', null));
    }

    public function testRemoveDeletesFeed(): void
    {
        $add = new AddAlertFeedHandler($this->feeds, new SequentialIdGenerator(), $this->clock, $this->eventBus);
        ($add)(new AddAlertFeed('tenant-1', 'RSS', 'https://proz.example/rss', null)); // id séquentiel => id-1

        $remove = new RemoveAlertFeedHandler($this->feeds, $this->clock, $this->eventBus);
        ($remove)(new RemoveAlertFeed('id-1'));

        self::assertSame(0, $this->feeds->count());
    }

    public function testRemoveUnknownThrowsNotFound(): void
    {
        $remove = new RemoveAlertFeedHandler($this->feeds, $this->clock, $this->eventBus);

        $this->expectException(AlertFeedNotFound::class);
        ($remove)(new RemoveAlertFeed('nope'));
    }

    public function testSetActiveTogglesFeed(): void
    {
        $add = new AddAlertFeedHandler($this->feeds, new SequentialIdGenerator(), $this->clock, $this->eventBus);
        ($add)(new AddAlertFeed('tenant-1', 'RSS', 'https://proz.example/rss', null));
        $id = $this->feeds->find(AlertFeedId::fromString('id-1'))?->id()->toString() ?? '';

        $setActive = new SetAlertFeedActiveHandler($this->feeds, $this->clock, $this->eventBus);
        ($setActive)(new SetAlertFeedActive($id, false));

        self::assertFalse($this->feeds->find(AlertFeedId::fromString('id-1'))?->isActive());
    }
}
