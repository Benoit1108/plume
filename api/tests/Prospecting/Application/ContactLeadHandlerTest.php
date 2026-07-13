<?php

declare(strict_types=1);

namespace App\Tests\Prospecting\Application;

use App\Prospecting\Application\Command\ContactLead\ContactLead;
use App\Prospecting\Application\Command\ContactLead\ContactLeadHandler;
use App\Prospecting\Domain\Lead\Event\LeadContacted;
use App\Prospecting\Domain\Lead\Exception\OrganizationNotContactable;
use App\Prospecting\Domain\Lead\Lead;
use App\Prospecting\Domain\Lead\LeadId;
use App\Prospecting\Domain\Lead\LeadSource;
use App\Prospecting\Domain\Lead\PipelineStatus;
use App\Prospecting\Domain\Lead\Priority;
use App\Prospecting\Infrastructure\Persistence\InMemory\InMemoryLeadRepository;
use App\Shared\Domain\ValueObject\LanguagePair;
use App\Shared\Domain\ValueObject\Segment;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\FakeOrganizationGateway;
use App\Tests\Support\FixedClock;
use App\Tests\Support\RecordingEventBus;
use PHPUnit\Framework\TestCase;

/** La garde RGPD s'applique aussi aux pistes créées AVANT le marquage doNotContact. */
final class ContactLeadHandlerTest extends TestCase
{
    private InMemoryLeadRepository $leads;
    private FakeOrganizationGateway $organizations;
    private RecordingEventBus $eventBus;
    private ContactLeadHandler $handler;

    protected function setUp(): void
    {
        $this->leads = new InMemoryLeadRepository();
        $this->organizations = new FakeOrganizationGateway();
        $this->eventBus = new RecordingEventBus();
        $this->handler = new ContactLeadHandler(
            $this->leads,
            $this->organizations,
            $this->eventBus,
            new FixedClock(new \DateTimeImmutable('2026-07-13 10:00:00')),
        );

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
        $lead->pullDomainEvents();
        $this->leads->save($lead);
    }

    public function testContactsWhenAllowed(): void
    {
        $this->organizations->add('org-1');

        ($this->handler)(new ContactLead('lead-1'));

        self::assertSame(PipelineStatus::CONTACTED, $this->leads->get(LeadId::fromString('lead-1'))->status());
        self::assertSame(1, $this->eventBus->countOf(LeadContacted::class));
    }

    public function testRefusesWhenOrganizationBecameDoNotContact(): void
    {
        $this->organizations->add('org-1', doNotContact: true);

        $this->expectException(OrganizationNotContactable::class);
        ($this->handler)(new ContactLead('lead-1'));

        self::assertSame(PipelineStatus::TO_CONTACT, $this->leads->get(LeadId::fromString('lead-1'))->status());
    }
}
