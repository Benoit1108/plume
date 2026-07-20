<?php

declare(strict_types=1);

namespace App\Tests\Sourcing\Application;

use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\TenantId;
use App\Sourcing\Application\Command\AcceptCandidate\AcceptCandidate;
use App\Sourcing\Application\Command\AcceptCandidate\AcceptCandidateHandler;
use App\Sourcing\Application\Command\MergeCandidate\MergeCandidate;
use App\Sourcing\Application\Command\MergeCandidate\MergeCandidateHandler;
use App\Sourcing\Application\Command\RejectCandidate\RejectCandidate;
use App\Sourcing\Application\Command\RejectCandidate\RejectCandidateHandler;
use App\Sourcing\Domain\CandidateLead\CandidateLead;
use App\Sourcing\Domain\CandidateLead\CandidateLeadId;
use App\Sourcing\Domain\CandidateLead\CandidateStatus;
use App\Sourcing\Domain\CandidateLead\Exception\CandidateAlreadyTriaged;
use App\Sourcing\Domain\CandidateLead\Exception\CandidateLeadNotFound;
use App\Sourcing\Domain\CandidateLead\Source;
use App\Tests\Support\FakeDirectoryGateway;
use App\Tests\Support\FakeProspectingGateway;
use App\Tests\Support\FixedClock;
use App\Tests\Support\InMemoryCandidateLeadRepository;
use App\Tests\Support\RecordingEventBus;
use App\Tests\Support\SequentialIdGenerator;
use PHPUnit\Framework\TestCase;

final class TriageCandidateHandlersTest extends TestCase
{
    private InMemoryCandidateLeadRepository $repo;
    private FakeDirectoryGateway $directory;
    private FakeProspectingGateway $prospecting;
    private FixedClock $clock;
    private RecordingEventBus $eventBus;
    private SequentialIdGenerator $ids;

    protected function setUp(): void
    {
        $this->repo = new InMemoryCandidateLeadRepository();
        $this->directory = new FakeDirectoryGateway();
        $this->prospecting = new FakeProspectingGateway();
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-07-15 10:00:00'));
        $this->eventBus = new RecordingEventBus();
        $this->ids = new SequentialIdGenerator();
    }

    private function seedCandidate(string $id = 'cand-1'): void
    {
        $candidate = CandidateLead::ingest(
            CandidateLeadId::fromString($id),
            TenantId::fromString('tenant-1'),
            Source::PROZ,
            'hash-abc',
            'Traduction littéraire',
            'Éditions Truc',
            'en>fr',
            null,
            null,
            null,
            $this->clock->now(),
        );
        $this->repo->save($candidate);
    }

    public function testAcceptCreatesOrganizationAndLeadThenMarksAccepted(): void
    {
        $this->seedCandidate();
        $handler = new AcceptCandidateHandler($this->repo, $this->directory, $this->prospecting, $this->ids, $this->clock, $this->eventBus);

        ($handler)(new AcceptCandidate('cand-1', 'Éditions Truc', 'PUBLISHER', 'en>fr', 'PUBLISHING', 'MEDIUM', 'https://truc.example'));

        self::assertCount(1, $this->directory->created);
        self::assertSame('Éditions Truc', $this->directory->created[0]['name']);
        self::assertSame('PUBLISHER', $this->directory->created[0]['type']);
        self::assertCount(1, $this->prospecting->created);
        self::assertSame('PROZ', $this->prospecting->created[0]['source']); // provenance fine (Source::PROZ)
        self::assertSame($this->directory->created[0]['organizationId'], $this->prospecting->created[0]['organizationId']);
        self::assertSame(CandidateStatus::ACCEPTED, $this->repo->find(CandidateLeadId::fromString('cand-1'))?->status());
    }

    public function testMergeCreatesLeadAgainstExistingOrganization(): void
    {
        $this->seedCandidate();
        $this->directory->addExisting('org-existing');
        $handler = new MergeCandidateHandler($this->repo, $this->directory, $this->prospecting, $this->ids, $this->clock, $this->eventBus);

        ($handler)(new MergeCandidate('cand-1', 'org-existing', 'en>fr', 'PUBLISHING', 'MEDIUM'));

        self::assertCount(1, $this->prospecting->created);
        self::assertSame('org-existing', $this->prospecting->created[0]['organizationId']);
        self::assertCount(0, $this->directory->created); // pas de nouvelle organisation
        self::assertSame(CandidateStatus::MERGED, $this->repo->find(CandidateLeadId::fromString('cand-1'))?->status());
    }

    public function testMergeAttachesToActiveLeadWithoutCreatingASecond(): void
    {
        $this->seedCandidate();
        $this->directory->addExisting('org-existing');
        $this->prospecting->withActiveLead('lead-active');
        $handler = new MergeCandidateHandler($this->repo, $this->directory, $this->prospecting, $this->ids, $this->clock, $this->eventBus);

        ($handler)(new MergeCandidate('cand-1', 'org-existing', 'en>fr', 'PUBLISHING', 'MEDIUM'));

        self::assertCount(0, $this->prospecting->created); // pas de seconde piste
        self::assertCount(1, $this->prospecting->notes);
        self::assertSame('lead-active', $this->prospecting->notes[0]['leadId']);
        self::assertSame(CandidateStatus::MERGED, $this->repo->find(CandidateLeadId::fromString('cand-1'))?->status());
    }

    public function testCannotTriageTwice(): void
    {
        $this->seedCandidate();
        $handler = new AcceptCandidateHandler($this->repo, $this->directory, $this->prospecting, $this->ids, $this->clock, $this->eventBus);
        ($handler)(new AcceptCandidate('cand-1', 'Éditions X', 'PUBLISHER', 'en>fr', 'PUBLISHING', 'MEDIUM'));

        $this->expectException(CandidateAlreadyTriaged::class);
        ($handler)(new AcceptCandidate('cand-1', 'Éditions X', 'PUBLISHER', 'en>fr', 'PUBLISHING', 'MEDIUM'));
    }

    public function testMergeRejectsUnknownOrganization(): void
    {
        $this->seedCandidate();
        $handler = new MergeCandidateHandler($this->repo, $this->directory, $this->prospecting, $this->ids, $this->clock, $this->eventBus);

        $this->expectException(InvalidValue::class);
        ($handler)(new MergeCandidate('cand-1', 'org-inconnue', 'en>fr', 'PUBLISHING', 'MEDIUM'));
    }

    public function testRejectMarksRejected(): void
    {
        $this->seedCandidate();
        $handler = new RejectCandidateHandler($this->repo, $this->clock, $this->eventBus);

        ($handler)(new RejectCandidate('cand-1'));

        self::assertSame(CandidateStatus::REJECTED, $this->repo->find(CandidateLeadId::fromString('cand-1'))?->status());
    }

    public function testRejectUnknownCandidateThrowsNotFound(): void
    {
        $handler = new RejectCandidateHandler($this->repo, $this->clock, $this->eventBus);

        $this->expectException(CandidateLeadNotFound::class);
        ($handler)(new RejectCandidate('nope'));
    }
}
