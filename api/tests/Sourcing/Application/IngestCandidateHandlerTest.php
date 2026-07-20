<?php

declare(strict_types=1);

namespace App\Tests\Sourcing\Application;

use App\Sourcing\Application\Command\IngestCandidate\IngestCandidate;
use App\Sourcing\Application\Command\IngestCandidate\IngestCandidateHandler;
use App\Sourcing\Domain\CandidateLead\CandidateLeadId;
use App\Sourcing\Domain\CandidateLead\Event\CandidateLeadIngested;
use App\Tests\Support\FixedClock;
use App\Tests\Support\InMemoryCandidateLeadRepository;
use App\Tests\Support\InMemoryRawAlertRepository;
use App\Tests\Support\RecordingEventBus;
use App\Tests\Support\SequentialIdGenerator;
use PHPUnit\Framework\TestCase;

final class IngestCandidateHandlerTest extends TestCase
{
    private InMemoryCandidateLeadRepository $repo;
    private InMemoryRawAlertRepository $rawAlerts;
    private RecordingEventBus $eventBus;
    private IngestCandidateHandler $handler;

    protected function setUp(): void
    {
        $this->repo = new InMemoryCandidateLeadRepository();
        $this->rawAlerts = new InMemoryRawAlertRepository();
        $this->eventBus = new RecordingEventBus();
        $this->handler = new IngestCandidateHandler(
            $this->repo,
            $this->rawAlerts,
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

    public function testKeepsRawPayloadAndLinksItToCandidate(): void
    {
        ($this->handler)(new IngestCandidate('tenant-1', 'RSS', 'Titre', 'Org', null, null, null, 'guid-1', null, '<item>brut</item>'));

        self::assertSame(1, $this->rawAlerts->count());
        $raw = $this->rawAlerts->saved[0];
        self::assertSame('<item>brut</item>', $raw->payload());

        // Le brut (id-1) est généré avant la candidate (id-2) ; la candidate le référence.
        $candidate = $this->repo->find(CandidateLeadId::fromString('id-2'));
        self::assertNotNull($candidate);
        self::assertSame($raw->id()->toString(), $candidate->rawRef());
    }

    public function testDuplicateKeepsNoAdditionalRaw(): void
    {
        $command = new IngestCandidate('tenant-1', 'RSS', 'Titre', 'Org', null, null, null, 'guid-1', null, '<item/>');
        ($this->handler)($command);
        ($this->handler)($command); // doublon → no-op, aucun 2e brut conservé

        self::assertSame(1, $this->repo->count());
        self::assertSame(1, $this->rawAlerts->count());
    }
}
