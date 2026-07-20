<?php

declare(strict_types=1);

namespace App\Tests\Sourcing\Application;

use App\Shared\Application\Command\Command;
use App\Shared\Domain\ValueObject\TenantId;
use App\Sourcing\Application\Command\IngestCandidate\IngestCandidate;
use App\Sourcing\Application\Command\IngestCandidate\IngestCandidateHandler;
use App\Sourcing\Application\Command\PollAlertSource\PollAlertSource;
use App\Sourcing\Application\Command\PollAlertSource\PollAlertSourceHandler;
use App\Sourcing\Application\Source\AlertSource;
use App\Sourcing\Application\Source\ParsedAlert;
use App\Sourcing\Domain\AlertFeed\AlertFeed;
use App\Sourcing\Domain\AlertFeed\AlertFeedId;
use App\Sourcing\Domain\CandidateLead\Source;
use App\Tests\Support\FixedClock;
use App\Tests\Support\HandlerMapCommandBus;
use App\Tests\Support\InMemoryAlertFeedRepository;
use App\Tests\Support\InMemoryCandidateLeadRepository;
use App\Tests\Support\InMemoryRawAlertRepository;
use App\Tests\Support\RecordingAlertSource;
use App\Tests\Support\RecordingEventBus;
use App\Tests\Support\SequentialIdGenerator;
use PHPUnit\Framework\TestCase;

final class PollAlertSourceHandlerTest extends TestCase
{
    private InMemoryCandidateLeadRepository $candidates;
    private InMemoryAlertFeedRepository $feeds;
    private RecordingAlertSource $realSource;
    private HandlerMapCommandBus $bus;

    protected function setUp(): void
    {
        $this->candidates = new InMemoryCandidateLeadRepository();
        $this->feeds = new InMemoryAlertFeedRepository();
        $this->realSource = new RecordingAlertSource();
        $ingest = new IngestCandidateHandler(
            $this->candidates,
            new InMemoryRawAlertRepository(),
            new SequentialIdGenerator(),
            new FixedClock(new \DateTimeImmutable('2026-07-20 10:00:00')),
            new RecordingEventBus(),
        );
        $this->bus = new HandlerMapCommandBus([
            IngestCandidate::class => function (Command $command) use ($ingest): void {
                \assert($command instanceof IngestCandidate);
                $ingest($command);
            },
        ]);
    }

    private function handler(): PollAlertSourceHandler
    {
        $demo = new class implements AlertSource {
            public function fetch(string $feedUrl): iterable
            {
                yield new ParsedAlert(Source::RSS->value, 'Démo A', null, null, null, null, 'demo-a');
                yield new ParsedAlert(Source::RSS->value, 'Démo B', null, null, null, null, 'demo-b');
            }
        };

        return new PollAlertSourceHandler($this->feeds, $this->realSource, $demo, $this->bus);
    }

    public function testWithoutFeedsFallsBackToTheDemoSource(): void
    {
        ($this->handler())(new PollAlertSource('tenant-1'));

        self::assertSame(2, $this->candidates->count()); // les 2 annonces de démo
        self::assertSame([], $this->realSource->fetchedUrls); // la source réelle n'est pas sollicitée
    }

    public function testPollsEachActiveFeedWithTheRealSource(): void
    {
        $this->feeds->save($this->feed('feed-1', 'https://a.test/rss'));
        $this->feeds->save($this->feed('feed-2', 'https://b.test/rss'));

        ($this->handler())(new PollAlertSource('tenant-1'));

        self::assertSame(2, $this->candidates->count());
        self::assertSame(['https://a.test/rss', 'https://b.test/rss'], $this->realSource->fetchedUrls);
    }

    public function testRepollIsDeduplicatedByExternalId(): void
    {
        $this->feeds->save($this->feed('feed-1', 'https://a.test/rss'));

        ($this->handler())(new PollAlertSource('tenant-1'));
        ($this->handler())(new PollAlertSource('tenant-1')); // même URL => même externalId => dédoublonné

        self::assertSame(1, $this->candidates->count());
    }

    private function feed(string $id, string $url): AlertFeed
    {
        return AlertFeed::add(
            AlertFeedId::fromString($id),
            TenantId::fromString('tenant-1'),
            Source::RSS,
            $url,
            null,
            new \DateTimeImmutable('2026-07-20 09:00:00'),
        );
    }
}
