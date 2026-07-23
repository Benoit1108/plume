<?php

declare(strict_types=1);

namespace App\Tests\Prospecting\Domain;

use App\Prospecting\Domain\Lead\Event\FollowUpCancelled;
use App\Prospecting\Domain\Lead\Event\FollowUpScheduled;
use App\Prospecting\Domain\Lead\Event\FollowUpSent;
use App\Prospecting\Domain\Lead\Event\LeadContacted;
use App\Prospecting\Domain\Lead\Event\LeadCreated;
use App\Prospecting\Domain\Lead\Event\LeadLost;
use App\Prospecting\Domain\Lead\Event\LeadMovedToSampleTest;
use App\Prospecting\Domain\Lead\Event\LeadPaused;
use App\Prospecting\Domain\Lead\Event\LeadResumed;
use App\Prospecting\Domain\Lead\Event\LeadReturnedToContact;
use App\Prospecting\Domain\Lead\Event\LeadWon;
use App\Prospecting\Domain\Lead\Event\NoteAdded;
use App\Prospecting\Domain\Lead\Event\ReplyReceived;
use App\Prospecting\Domain\Lead\Exception\FollowUpNotAllowed;
use App\Prospecting\Domain\Lead\Exception\IllegalStatusTransition;
use App\Prospecting\Domain\Lead\FollowUpStatus;
use App\Prospecting\Domain\Lead\Lead;
use App\Prospecting\Domain\Lead\LeadId;
use App\Prospecting\Domain\Lead\LeadSource;
use App\Prospecting\Domain\Lead\PipelineStatus;
use App\Prospecting\Domain\Lead\Priority;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\LanguagePair;
use App\Shared\Domain\ValueObject\Segment;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

/**
 * Test de domaine pur : aucune base de données, aucun conteneur Symfony.
 * La machine à états est testée exhaustivement (chemins légaux ET illégaux).
 */
final class LeadTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-07-13 10:00:00');
    }

    private function aLead(): Lead
    {
        return Lead::create(
            LeadId::fromString('11111111-1111-1111-1111-111111111111'),
            TenantId::fromString('tenant-1'),
            'org-1',
            'contact-1',
            LanguagePair::fromString('en>fr'),
            LeadSource::DIRECT,
            Priority::HIGH,
            Segment::PUBLISHING,
            $this->now,
        );
    }

    /** Amène une piste dans le statut voulu par des transitions légales. */
    private function aLeadIn(PipelineStatus $status): Lead
    {
        $lead = $this->aLead();
        if (PipelineStatus::FOLLOWED_UP === $status) {
            self::fail('FOLLOWED_UP arrive en M1.3 (relances).');
        }
        if (\in_array($status, [PipelineStatus::CONTACTED, PipelineStatus::IN_DISCUSSION, PipelineStatus::SAMPLE_TEST, PipelineStatus::WON], true)) {
            $lead->contact($this->now);
        }
        if (\in_array($status, [PipelineStatus::IN_DISCUSSION, PipelineStatus::SAMPLE_TEST, PipelineStatus::WON], true)) {
            $lead->recordReply($this->now);
        }
        if (PipelineStatus::SAMPLE_TEST === $status) {
            $lead->moveToSampleTest($this->now);
        }
        if (PipelineStatus::WON === $status) {
            $lead->markWon($this->now);
        }
        if (PipelineStatus::LOST === $status) {
            $lead->markLost($this->now);
        }
        if (PipelineStatus::PAUSED === $status) {
            $lead->pause($this->now);
        }
        $lead->pullDomainEvents();

        return $lead;
    }

    public function testCreationStartsInToContactWithEnrichedEvent(): void
    {
        $lead = $this->aLead();

        self::assertSame(PipelineStatus::TO_CONTACT, $lead->status());
        self::assertSame('en>fr', $lead->languagePair()->toString());
        self::assertSame(LeadSource::DIRECT, $lead->source());
        self::assertSame(Priority::HIGH, $lead->priority());
        self::assertSame('contact-1', $lead->contactId());
        self::assertEquals($this->now, $lead->createdAt());

        $events = $lead->pullDomainEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(LeadCreated::class, $event);
        self::assertSame('tenant-1', $event->tenantId);
        self::assertSame('org-1', $event->organizationId);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/', $event->eventId());
    }

    public function testHappyPathToWon(): void
    {
        $lead = $this->aLead();
        $lead->pullDomainEvents();

        $lead->contact($this->now);
        self::assertEquals($this->now, $lead->lastContactedAt());

        $lead->recordReply($this->now->modify('+1 day'));
        self::assertEquals($this->now->modify('+1 day'), $lead->lastReplyAt());

        $lead->moveToSampleTest($this->now->modify('+2 days'));
        $lead->markWon($this->now->modify('+10 days'));

        self::assertSame(PipelineStatus::WON, $lead->status());
        // La cadence vit avec le pipeline : relance auto au contact, annulée à la réponse.
        self::assertSame(
            [LeadContacted::class, FollowUpScheduled::class, ReplyReceived::class, FollowUpCancelled::class, LeadMovedToSampleTest::class, LeadWon::class],
            array_map(get_class(...), $lead->pullDomainEvents()),
        );
    }

    public function testLostFromAnyActiveStatus(): void
    {
        foreach ([PipelineStatus::TO_CONTACT, PipelineStatus::CONTACTED, PipelineStatus::IN_DISCUSSION, PipelineStatus::SAMPLE_TEST] as $status) {
            $lead = $this->aLeadIn($status);
            $lead->markLost($this->now);
            self::assertSame(PipelineStatus::LOST, $lead->status(), sprintf('LOST depuis %s', $status->value));
            self::assertInstanceOf(LeadLost::class, $lead->pullDomainEvents()[0]);
        }
    }

    public function testTerminalStatusesRefuseEverything(): void
    {
        foreach ([PipelineStatus::WON, PipelineStatus::LOST] as $terminal) {
            $lead = $this->aLeadIn($terminal);
            try {
                $lead->contact($this->now);
                self::fail(sprintf('contact() devrait être refusé depuis %s', $terminal->value));
            } catch (IllegalStatusTransition) {
                self::assertSame($terminal, $lead->status());
            }
        }
    }

    public function testIllegalTransitionsAreRefused(): void
    {
        // Réponse sans contact préalable.
        $lead = $this->aLead();
        try {
            $lead->recordReply($this->now);
            self::fail('recordReply depuis TO_CONTACT devrait être refusé');
        } catch (IllegalStatusTransition) {
        }

        // Test/échantillon sans discussion.
        $lead = $this->aLeadIn(PipelineStatus::CONTACTED);
        try {
            $lead->moveToSampleTest($this->now);
            self::fail('moveToSampleTest depuis CONTACTED devrait être refusé');
        } catch (IllegalStatusTransition) {
        }

        // Gagner sans discussion.
        $lead = $this->aLeadIn(PipelineStatus::CONTACTED);
        try {
            $lead->markWon($this->now);
            self::fail('markWon depuis CONTACTED devrait être refusé');
        } catch (IllegalStatusTransition) {
        }

        // Pause pendant un test/échantillon (phase courte non interruptible).
        $lead = $this->aLeadIn(PipelineStatus::SAMPLE_TEST);
        $this->expectException(IllegalStatusTransition::class);
        $lead->pause($this->now);
    }

    public function testReturnToContactCorrectsAMistakenContact(): void
    {
        $lead = $this->aLeadIn(PipelineStatus::CONTACTED);
        self::assertNotNull($lead->lastContactedAt());

        $lead->returnToContact($this->now);

        self::assertSame(PipelineStatus::TO_CONTACT, $lead->status());
        self::assertNull($lead->lastContactedAt(), 'la date de contact est effacée');

        $classes = array_map(get_class(...), $lead->pullDomainEvents());
        self::assertContains(FollowUpCancelled::class, $classes, 'la relance auto planifiée est annulée');
        self::assertContains(LeadReturnedToContact::class, $classes);
    }

    public function testReturnToContactRefusedOnceDiscussionStarted(): void
    {
        $lead = $this->aLeadIn(PipelineStatus::IN_DISCUSSION);
        $this->expectException(IllegalStatusTransition::class);
        $lead->returnToContact($this->now);
    }

    public function testPauseThenResumeRestoresPreviousStatus(): void
    {
        $lead = $this->aLeadIn(PipelineStatus::IN_DISCUSSION);

        $lead->pause($this->now);
        self::assertSame(PipelineStatus::PAUSED, $lead->status());

        $lead->resume($this->now);
        self::assertSame(PipelineStatus::IN_DISCUSSION, $lead->status());
        self::assertNull($lead->statusBeforePause());

        $events = $lead->pullDomainEvents();
        self::assertInstanceOf(LeadPaused::class, $events[0]);
        self::assertSame('IN_DISCUSSION', $events[0]->pausedFrom);
        self::assertInstanceOf(LeadResumed::class, $events[1]);
        self::assertSame('IN_DISCUSSION', $events[1]->resumedTo);
    }

    public function testNoteIsCarriedByTheEventOnly(): void
    {
        $lead = $this->aLead();
        $lead->pullDomainEvents();

        $lead->addNote('  Rappeler après le salon du livre.  ', $this->now);

        $events = $lead->pullDomainEvents();
        self::assertInstanceOf(NoteAdded::class, $events[0]);
        self::assertSame('Rappeler après le salon du livre.', $events[0]->text);
    }

    public function testEmptyNoteIsRejected(): void
    {
        $lead = $this->aLead();

        $this->expectException(InvalidValue::class);
        $lead->addNote('   ', $this->now);
    }

    public function testNotesAreAllowedInTerminalStatuses(): void
    {
        $lead = $this->aLeadIn(PipelineStatus::LOST);

        $lead->addNote('Perdue : budget gelé — retenter en 2027.', $this->now);

        self::assertCount(1, $lead->pullDomainEvents());
    }

    // ----- Relances (M1.3) -----

    public function testContactAutoSchedulesFirstFollowUpAtSevenDays(): void
    {
        $lead = $this->aLead();
        $lead->pullDomainEvents();

        $lead->contact($this->now);

        self::assertSame('2026-07-20', $lead->nextFollowUpAt()?->format('Y-m-d'));
        $events = $lead->pullDomainEvents();
        self::assertInstanceOf(FollowUpScheduled::class, $events[1]);
        self::assertTrue($events[1]->auto);
        self::assertSame('2026-07-20', $events[1]->dueAt);
    }

    public function testFollowUpCadenceProgressesThenStops(): void
    {
        $lead = $this->aLeadIn(PipelineStatus::CONTACTED); // relance auto J+7 en attente

        $lead->recordFollowUp($this->now); // 1 faite -> J+21
        self::assertSame(PipelineStatus::FOLLOWED_UP, $lead->status());
        self::assertSame('2026-08-03', $lead->nextFollowUpAt()?->format('Y-m-d'));

        $lead->recordFollowUp($this->now); // 2 faites -> J+45
        self::assertSame('2026-08-27', $lead->nextFollowUpAt()?->format('Y-m-d'));

        $lead->recordFollowUp($this->now); // 3 faites -> fin de cadence
        self::assertNull($lead->nextFollowUpAt());

        $events = $lead->pullDomainEvents();
        self::assertSame(3, \count(array_filter($events, static fn (object $e): bool => $e instanceof FollowUpSent)));
        self::assertSame(2, \count(array_filter($events, static fn (object $e): bool => $e instanceof FollowUpScheduled)));
    }

    public function testReplyCancelsPendingFollowUp(): void
    {
        $lead = $this->aLeadIn(PipelineStatus::CONTACTED);

        $lead->recordReply($this->now);

        self::assertNull($lead->nextFollowUpAt());
        $events = $lead->pullDomainEvents();
        self::assertInstanceOf(FollowUpCancelled::class, $events[1]);
        self::assertSame('REPLY', $events[1]->reason);
    }

    public function testRecordReplyIsIdempotent(): void
    {
        // Dette revue fin M1 soldée (M2.3) : les réponses captées automatiquement
        // arrivent en double — une piste en discussion absorbe sans bruit.
        $lead = $this->aLeadIn(PipelineStatus::CONTACTED);
        $lead->recordReply($this->now, 'Merci, envoyez vos références.');
        $lead->pullDomainEvents();

        $lead->recordReply($this->now, 'Relève suivante — même fil.');

        self::assertSame(PipelineStatus::IN_DISCUSSION, $lead->status());
        self::assertSame([], $lead->pullDomainEvents()); // no-op : aucun event
    }

    public function testReplyPreviewTravelsInTheEvent(): void
    {
        $lead = $this->aLeadIn(PipelineStatus::CONTACTED);

        $lead->recordReply($this->now, 'Merci pour votre message.');

        $events = $lead->pullDomainEvents();
        $reply = array_values(array_filter($events, static fn (object $e): bool => $e instanceof ReplyReceived))[0];
        self::assertSame('Merci pour votre message.', $reply->preview);
    }

    public function testTerminalAndPauseCancelPendingFollowUp(): void
    {
        $lead = $this->aLeadIn(PipelineStatus::CONTACTED);
        $lead->markLost($this->now);
        self::assertNull($lead->nextFollowUpAt());

        $lead = $this->aLeadIn(PipelineStatus::CONTACTED);
        $lead->pause($this->now);
        self::assertNull($lead->nextFollowUpAt());
        $lead->resume($this->now); // la reprise ne replanifie pas seule
        self::assertNull($lead->nextFollowUpAt());
    }

    public function testManualRescheduleReplacesPendingFollowUp(): void
    {
        $lead = $this->aLeadIn(PipelineStatus::CONTACTED);

        $lead->scheduleFollowUp($this->now->modify('+2 days'), '  Relancer après le salon  ', $this->now);

        self::assertSame('2026-07-15', $lead->nextFollowUpAt()?->format('Y-m-d'));
        self::assertSame('Relancer après le salon', $lead->nextFollowUpLabel());
        // Une seule PENDING : l'auto J+7 a été remplacée, pas empilée.
        self::assertCount(1, array_filter($lead->followUps(), static fn ($f): bool => $f->isPending()));
    }

    public function testFollowUpScheduledTodayIsAcceptedButPastIsRejected(): void
    {
        $lead = $this->aLeadIn(PipelineStatus::CONTACTED);

        $lead->scheduleFollowUp($this->now, null, $this->now); // aujourd'hui : OK
        self::assertSame('2026-07-13', $lead->nextFollowUpAt()?->format('Y-m-d'));

        $this->expectException(InvalidValue::class);
        $lead->scheduleFollowUp($this->now->modify('-1 day'), null, $this->now);
    }

    public function testNoFollowUpOnTerminalOrPausedLead(): void
    {
        $lead = $this->aLeadIn(PipelineStatus::PAUSED);
        try {
            $lead->scheduleFollowUp($this->now->modify('+3 days'), null, $this->now);
            self::fail('scheduleFollowUp devrait être refusé en pause');
        } catch (FollowUpNotAllowed) {
        }

        $lead = $this->aLeadIn(PipelineStatus::WON);
        $this->expectException(FollowUpNotAllowed::class);
        $lead->scheduleFollowUp($this->now->modify('+3 days'), null, $this->now);
    }

    public function testManualCancelIsIdempotent(): void
    {
        $lead = $this->aLeadIn(PipelineStatus::CONTACTED);

        $lead->cancelFollowUp($this->now);
        self::assertNull($lead->nextFollowUpAt());
        self::assertInstanceOf(FollowUpCancelled::class, $lead->pullDomainEvents()[0]);

        $lead->cancelFollowUp($this->now); // plus rien à annuler : aucun event
        self::assertCount(0, $lead->pullDomainEvents());
    }

    public function testFollowUpWithoutScheduleStillCountsInCadence(): void
    {
        $lead = $this->aLeadIn(PipelineStatus::CONTACTED);
        $lead->cancelFollowUp($this->now); // plus de PENDING
        $lead->pullDomainEvents();

        $lead->recordFollowUp($this->now); // consignée DONE + cadence continue (J+21)

        self::assertSame('2026-08-03', $lead->nextFollowUpAt()?->format('Y-m-d'));
        self::assertSame(1, \count(array_filter($lead->followUps(), static fn ($f): bool => FollowUpStatus::DONE === $f->status())));
    }
}
