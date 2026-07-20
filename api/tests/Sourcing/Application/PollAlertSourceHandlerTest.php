<?php

declare(strict_types=1);

namespace App\Tests\Sourcing\Application;

use App\Shared\Application\Command\Command;
use App\Sourcing\Application\Command\IngestCandidate\IngestCandidate;
use App\Sourcing\Application\Command\IngestCandidate\IngestCandidateHandler;
use App\Sourcing\Application\Command\PollAlertSource\PollAlertSource;
use App\Sourcing\Application\Command\PollAlertSource\PollAlertSourceHandler;
use App\Sourcing\Application\Source\AlertSource;
use App\Sourcing\Application\Source\ParsedAlert;
use App\Tests\Support\FixedClock;
use App\Tests\Support\HandlerMapCommandBus;
use App\Tests\Support\InMemoryCandidateLeadRepository;
use App\Tests\Support\InMemoryRawAlertRepository;
use App\Tests\Support\RecordingEventBus;
use App\Tests\Support\SequentialIdGenerator;
use PHPUnit\Framework\TestCase;

final class PollAlertSourceHandlerTest extends TestCase
{
    private InMemoryCandidateLeadRepository $repo;
    private InMemoryRawAlertRepository $rawAlerts;
    private PollAlertSourceHandler $handler;

    protected function setUp(): void
    {
        $this->repo = new InMemoryCandidateLeadRepository();
        $this->rawAlerts = new InMemoryRawAlertRepository();
        // Le bus route chaque IngestCandidate vers le vrai handler (intégration application).
        $ingest = new IngestCandidateHandler(
            $this->repo,
            $this->rawAlerts,
            new SequentialIdGenerator(),
            new FixedClock(new \DateTimeImmutable('2026-07-20 10:00:00')),
            new RecordingEventBus(),
        );
        $bus = new HandlerMapCommandBus([
            IngestCandidate::class => static function (Command $command) use ($ingest): void {
                \assert($command instanceof IngestCandidate);
                $ingest($command);
            },
        ]);
        $this->handler = new PollAlertSourceHandler($this->twoItemSource(), $bus);
    }

    public function testEachParsedAlertBecomesACandidateWithItsRawKept(): void
    {
        ($this->handler)(new PollAlertSource('tenant-1'));

        self::assertSame(2, $this->repo->count());
        self::assertSame(2, $this->rawAlerts->count());
    }

    public function testRepollIsDeduplicatedByExternalId(): void
    {
        ($this->handler)(new PollAlertSource('tenant-1'));
        ($this->handler)(new PollAlertSource('tenant-1')); // mêmes guid → aucun doublon

        self::assertSame(2, $this->repo->count());
        self::assertSame(2, $this->rawAlerts->count());
    }

    private function twoItemSource(): AlertSource
    {
        return new class implements AlertSource {
            public function fetch(): iterable
            {
                yield new ParsedAlert('RSS', 'Annonce A', 'Org A', 'en>fr', 'https://a.test', 'extrait A', 'guid-a', null, '<item>a</item>');
                yield new ParsedAlert('RSS', 'Annonce B', 'Org B', 'es>fr', 'https://b.test', 'extrait B', 'guid-b', null, '<item>b</item>');
            }
        };
    }
}
