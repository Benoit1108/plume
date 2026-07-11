<?php

declare(strict_types=1);

namespace App\Tests\Prospecting\Domain;

use App\Prospecting\Domain\Lead\Event\LeadContacted;
use App\Prospecting\Domain\Lead\Event\LeadCreated;
use App\Prospecting\Domain\Lead\Exception\IllegalStatusTransition;
use App\Prospecting\Domain\Lead\Lead;
use App\Prospecting\Domain\Lead\LeadId;
use App\Prospecting\Domain\Lead\PipelineStatus;
use App\Prospecting\Domain\Lead\Segment;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

/**
 * Test de domaine pur : aucune base de données, aucun conteneur Symfony.
 * C'est un objectif de conception (cf. CLAUDE.md), pas un accident.
 */
final class LeadTest extends TestCase
{
    private function aLead(): Lead
    {
        return Lead::create(
            LeadId::fromString('11111111-1111-1111-1111-111111111111'),
            TenantId::fromString('tenant-1'),
            'org-1',
            Segment::PUBLISHING,
            new \DateTimeImmutable('2026-07-11 10:00:00'),
        );
    }

    public function testCreationStartsInToContactAndRecordsEvent(): void
    {
        $lead = $this->aLead();

        self::assertSame(PipelineStatus::TO_CONTACT, $lead->status());

        $events = $lead->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(LeadCreated::class, $events[0]);
    }

    public function testContactMovesToContactedAndRecordsEvent(): void
    {
        $lead = $this->aLead();
        $lead->pullDomainEvents(); // vide l'événement de création

        $lead->contact(new \DateTimeImmutable('2026-07-11 11:00:00'));

        self::assertSame(PipelineStatus::CONTACTED, $lead->status());

        $events = $lead->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(LeadContacted::class, $events[0]);
    }

    public function testCannotContactAnAlreadyContactedLead(): void
    {
        $lead = $this->aLead();
        $lead->contact(new \DateTimeImmutable('2026-07-11 11:00:00'));

        $this->expectException(IllegalStatusTransition::class);
        $lead->contact(new \DateTimeImmutable('2026-07-11 12:00:00'));
    }

    public function testWonAndLostAreTerminal(): void
    {
        self::assertTrue(PipelineStatus::WON->isTerminal());
        self::assertTrue(PipelineStatus::LOST->isTerminal());
        self::assertSame([], PipelineStatus::WON->allowedTransitions());
    }
}
