<?php

declare(strict_types=1);

namespace App\Tests\Prospecting\Application;

use App\Prospecting\Application\Command\CancelFollowUp\CancelFollowUp;
use App\Prospecting\Application\Command\CancelFollowUp\CancelFollowUpHandler;
use App\Prospecting\Application\Command\RecordFollowUp\RecordFollowUp;
use App\Prospecting\Application\Command\RecordFollowUp\RecordFollowUpHandler;
use App\Prospecting\Application\Command\ScheduleFollowUp\ScheduleFollowUp;
use App\Prospecting\Application\Command\ScheduleFollowUp\ScheduleFollowUpHandler;
use App\Prospecting\Domain\Lead\Event\FollowUpScheduled;
use App\Prospecting\Domain\Lead\Event\FollowUpSent;
use App\Prospecting\Domain\Lead\Lead;
use App\Prospecting\Domain\Lead\LeadId;
use App\Prospecting\Domain\Lead\LeadSource;
use App\Prospecting\Domain\Lead\PipelineStatus;
use App\Prospecting\Domain\Lead\Priority;
use App\Prospecting\Infrastructure\Persistence\InMemory\InMemoryLeadRepository;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\LanguagePair;
use App\Shared\Domain\ValueObject\Segment;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\FixedClock;
use App\Tests\Support\RecordingEventBus;
use PHPUnit\Framework\TestCase;

/** Tests d'application des relances : repo in-memory, sans base. */
final class FollowUpHandlersTest extends TestCase
{
    private InMemoryLeadRepository $leads;
    private RecordingEventBus $eventBus;
    private FixedClock $clock;

    protected function setUp(): void
    {
        $this->leads = new InMemoryLeadRepository();
        $this->eventBus = new RecordingEventBus();
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-07-13 10:00:00'));

        $lead = Lead::create(
            LeadId::fromString('lead-1'),
            TenantId::fromString('tenant-1'),
            'org-1',
            null,
            LanguagePair::fromString('en>fr'),
            LeadSource::DIRECT,
            Priority::MEDIUM,
            Segment::PUBLISHING,
            new \DateTimeImmutable('2026-07-13 09:00:00'),
        );
        $lead->contact($this->clock->now()); // relance auto J+7 en attente
        $lead->pullDomainEvents();
        $this->leads->save($lead);
    }

    public function testRecordFollowUpAdvancesCadence(): void
    {
        (new RecordFollowUpHandler($this->leads, $this->eventBus, $this->clock))(new RecordFollowUp('lead-1'));

        $lead = $this->leads->get(LeadId::fromString('lead-1'));
        self::assertSame(PipelineStatus::FOLLOWED_UP, $lead->status());
        self::assertSame('2026-08-03', $lead->nextFollowUpAt()?->format('Y-m-d'));
        self::assertSame(1, $this->eventBus->countOf(FollowUpSent::class));
        self::assertSame(1, $this->eventBus->countOf(FollowUpScheduled::class));
    }

    public function testScheduleFollowUpParsesDateAndReplaces(): void
    {
        (new ScheduleFollowUpHandler($this->leads, $this->eventBus, $this->clock))(
            new ScheduleFollowUp('lead-1', '2026-07-15', 'Après le salon'),
        );

        $lead = $this->leads->get(LeadId::fromString('lead-1'));
        self::assertSame('2026-07-15', $lead->nextFollowUpAt()?->format('Y-m-d'));
        self::assertSame('Après le salon', $lead->nextFollowUpLabel());
    }

    public function testScheduleFollowUpRejectsMalformedDate(): void
    {
        $this->expectException(InvalidValue::class);
        (new ScheduleFollowUpHandler($this->leads, $this->eventBus, $this->clock))(
            new ScheduleFollowUp('lead-1', '15/07/2026', null),
        );
    }

    public function testCancelFollowUpClearsSchedule(): void
    {
        (new CancelFollowUpHandler($this->leads, $this->eventBus, $this->clock))(new CancelFollowUp('lead-1'));

        self::assertNull($this->leads->get(LeadId::fromString('lead-1'))->nextFollowUpAt());
    }
}
