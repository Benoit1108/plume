<?php

declare(strict_types=1);

namespace App\Tests\Sourcing\Domain;

use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\TenantId;
use App\Sourcing\Domain\CandidateLead\CandidateLead;
use App\Sourcing\Domain\CandidateLead\CandidateLeadId;
use App\Sourcing\Domain\CandidateLead\CandidateStatus;
use App\Sourcing\Domain\CandidateLead\Event\CandidateLeadAccepted;
use App\Sourcing\Domain\CandidateLead\Event\CandidateLeadIngested;
use App\Sourcing\Domain\CandidateLead\Event\CandidateLeadMerged;
use App\Sourcing\Domain\CandidateLead\Event\CandidateLeadRejected;
use App\Sourcing\Domain\CandidateLead\Exception\CandidateAlreadyTriaged;
use App\Sourcing\Domain\CandidateLead\Source;
use PHPUnit\Framework\TestCase;

final class CandidateLeadTest extends TestCase
{
    private const NOW = '2026-07-15 10:00:00';

    private function ingest(): CandidateLead
    {
        return CandidateLead::ingest(
            CandidateLeadId::fromString('cand-1'),
            TenantId::fromString('tenant-1'),
            Source::PROZ,
            'hash-abc',
            '  Traduction littéraire EN>FR  ',
            '  Éditions Truc  ',
            'en>fr',
            'https://proz.example/job/1',
            '  extrait  ',
            new \DateTimeImmutable('2026-07-14 09:00:00'),
            new \DateTimeImmutable(self::NOW),
        );
    }

    public function testIngestCreatesPendingCandidateWithNormalizedFieldsAndEvent(): void
    {
        $candidate = $this->ingest();

        self::assertSame(CandidateStatus::PENDING, $candidate->status());
        self::assertSame('Traduction littéraire EN>FR', $candidate->title());
        self::assertSame('Éditions Truc', $candidate->organizationName());
        self::assertSame(Source::PROZ, $candidate->source());
        self::assertSame('hash-abc', $candidate->dedupHash());

        $events = $candidate->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CandidateLeadIngested::class, $events[0]);
    }

    public function testBlankOptionalFieldsBecomeNull(): void
    {
        $candidate = CandidateLead::ingest(
            CandidateLeadId::fromString('cand-2'),
            TenantId::fromString('tenant-1'),
            Source::MANUAL,
            'hash-2',
            'Titre',
            '   ',
            null,
            '',
            null,
            null,
            new \DateTimeImmutable(self::NOW),
        );

        self::assertNull($candidate->organizationName());
        self::assertNull($candidate->languagePair());
        self::assertNull($candidate->url());
    }

    public function testEmptyTitleIsRejected(): void
    {
        $this->expectException(InvalidValue::class);
        CandidateLead::ingest(
            CandidateLeadId::fromString('cand-3'),
            TenantId::fromString('tenant-1'),
            Source::RSS,
            'hash-3',
            '   ',
            null,
            null,
            null,
            null,
            null,
            new \DateTimeImmutable(self::NOW),
        );
    }

    public function testAcceptPromotesAndRecordsEvent(): void
    {
        $candidate = $this->ingest();
        $candidate->pullDomainEvents();

        $candidate->accept('lead-9', 'org-9', new \DateTimeImmutable(self::NOW));

        self::assertSame(CandidateStatus::ACCEPTED, $candidate->status());
        self::assertSame('lead-9', $candidate->promotedLeadId());
        self::assertSame('org-9', $candidate->organizationId());
        $events = $candidate->pullDomainEvents();
        self::assertInstanceOf(CandidateLeadAccepted::class, $events[0]);
    }

    public function testMergeRecordsEvent(): void
    {
        $candidate = $this->ingest();
        $candidate->pullDomainEvents();

        $candidate->merge('lead-9', 'org-existing', new \DateTimeImmutable(self::NOW));

        self::assertSame(CandidateStatus::MERGED, $candidate->status());
        self::assertInstanceOf(CandidateLeadMerged::class, $candidate->pullDomainEvents()[0]);
    }

    public function testRejectRecordsEvent(): void
    {
        $candidate = $this->ingest();
        $candidate->pullDomainEvents();

        $candidate->reject(new \DateTimeImmutable(self::NOW));

        self::assertSame(CandidateStatus::REJECTED, $candidate->status());
        self::assertInstanceOf(CandidateLeadRejected::class, $candidate->pullDomainEvents()[0]);
    }

    public function testSourceMapsToLeadSource(): void
    {
        self::assertSame('PROZ', Source::PROZ->toLeadSource());
        self::assertSame('RSS', Source::RSS->toLeadSource());
        self::assertSame('JOB_BOARD', Source::MANUAL->toLeadSource()); // seul mapping non-identité
    }

    public function testIngestCanCarryARawReference(): void
    {
        $candidate = CandidateLead::ingest(
            CandidateLeadId::fromString('cand-raw'),
            TenantId::fromString('tenant-1'),
            Source::RSS,
            'hash-raw',
            'Titre',
            null,
            null,
            null,
            null,
            null,
            new \DateTimeImmutable(self::NOW),
            'raw-123',
        );

        self::assertSame('raw-123', $candidate->rawRef());
    }

    public function testRawReferenceDefaultsToNull(): void
    {
        self::assertNull($this->ingest()->rawRef());
    }

    public function testCannotRetriageOnceTriaged(): void
    {
        $candidate = $this->ingest();
        $candidate->accept('lead-9', 'org-9', new \DateTimeImmutable(self::NOW));

        $this->expectException(CandidateAlreadyTriaged::class);
        $candidate->reject(new \DateTimeImmutable(self::NOW));
    }
}
