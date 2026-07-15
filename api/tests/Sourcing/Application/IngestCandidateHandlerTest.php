<?php

declare(strict_types=1);

namespace App\Tests\Sourcing\Application;

use App\Sourcing\Application\Command\IngestCandidate\IngestCandidate;
use App\Sourcing\Application\Command\IngestCandidate\IngestCandidateHandler;
use App\Sourcing\Domain\CandidateLead\Event\CandidateLeadIngested;
use App\Tests\Support\FixedClock;
use App\Tests\Support\InMemoryCandidateLeadRepository;
use App\Tests\Support\RecordingEventBus;
use App\Tests\Support\SequentialIdGenerator;
use PHPUnit\Framework\TestCase;

final class IngestCandidateHandlerTest extends TestCase
{
    private InMemoryCandidateLeadRepository $repo;
    private RecordingEventBus $eventBus;
    private IngestCandidateHandler $handler;

    protected function setUp(): void
    {
        $this->repo = new InMemoryCandidateLeadRepository();
        $this->eventBus = new RecordingEventBus();
        $this->handler = new IngestCandidateHandler(
            $this->repo,
            new SequentialIdGenerator(),
            new FixedClock(new \DateTimeImmutable('2026-07-15 10:00:00')),
            $this->eventBus,
        );
    }

    public function testIngestsPendingCandidate(): void
    {
        ($this->handler)(new IngestCandidate('tenant-1', 'PROZ', 'Traduction EN>FR', 'Éditions Truc', 'en>fr'));

        self::assertSame(1, $this->repo->count());
        self::assertSame(1, $this->eventBus->countOf(CandidateLeadIngested::class));
    }

    public function testDuplicateIngestionIsNoOp(): void
    {
        $command = new IngestCandidate('tenant-1', 'PROZ', 'Traduction EN>FR', 'Éditions Truc', 'en>fr');
        ($this->handler)($command);
        ($this->handler)($command); // même empreinte → no-op

        self::assertSame(1, $this->repo->count());
        self::assertSame(1, $this->eventBus->countOf(CandidateLeadIngested::class));
    }

    public function testSameExternalIdDeduplicatesAcrossDifferentTitles(): void
    {
        ($this->handler)(new IngestCandidate('tenant-1', 'RSS', 'Titre A', 'Org', null, null, null, 'guid-42'));
        ($this->handler)(new IngestCandidate('tenant-1', 'RSS', 'Titre B (édité)', 'Org', null, null, null, 'guid-42'));

        self::assertSame(1, $this->repo->count());
    }
}
