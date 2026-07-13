<?php

declare(strict_types=1);

namespace App\Tests\Prospecting\Application;

use App\Prospecting\Application\Command\CreateLead\CreateLead;
use App\Prospecting\Application\Command\CreateLead\CreateLeadHandler;
use App\Prospecting\Domain\Lead\Event\LeadCreated;
use App\Prospecting\Domain\Lead\Exception\ActiveLeadAlreadyExists;
use App\Prospecting\Domain\Lead\Exception\OrganizationNotContactable;
use App\Prospecting\Domain\Lead\LeadId;
use App\Prospecting\Domain\Lead\PipelineStatus;
use App\Prospecting\Infrastructure\Persistence\InMemory\InMemoryLeadRepository;
use App\Shared\Domain\Exception\InvalidValue;
use App\Tests\Support\FakeOrganizationGateway;
use App\Tests\Support\FixedClock;
use App\Tests\Support\RecordingEventBus;
use PHPUnit\Framework\TestCase;

/** Test d'application : repo in-memory + gateway factice, sans base ni conteneur. */
final class CreateLeadHandlerTest extends TestCase
{
    private InMemoryLeadRepository $leads;
    private FakeOrganizationGateway $organizations;
    private RecordingEventBus $eventBus;
    private CreateLeadHandler $handler;

    protected function setUp(): void
    {
        $this->leads = new InMemoryLeadRepository();
        $this->organizations = new FakeOrganizationGateway();
        $this->eventBus = new RecordingEventBus();
        $this->handler = new CreateLeadHandler(
            $this->leads,
            $this->organizations,
            $this->eventBus,
            new FixedClock(new \DateTimeImmutable('2026-07-13 10:00:00')),
        );
    }

    private function aCommand(string $organizationId = 'org-1', ?string $contactId = null): CreateLead
    {
        return new CreateLead('lead-1', 'tenant-1', $organizationId, $contactId, 'en>fr', 'DIRECT', 'HIGH', 'PUBLISHING');
    }

    public function testCreatesLeadAndPublishesEvent(): void
    {
        $this->organizations->add('org-1', contactIds: 'c-1');

        ($this->handler)($this->aCommand(contactId: 'c-1'));

        $lead = $this->leads->get(LeadId::fromString('lead-1'));
        self::assertSame(PipelineStatus::TO_CONTACT, $lead->status());
        self::assertSame('en>fr', $lead->languagePair()->toString());
        self::assertSame(1, $this->eventBus->countOf(LeadCreated::class));
    }

    public function testRejectsUnknownOrganization(): void
    {
        $this->expectException(InvalidValue::class);
        ($this->handler)($this->aCommand('org-inconnue'));
    }

    public function testRejectsDoNotContactOrganization(): void
    {
        $this->organizations->add('org-1', doNotContact: true);

        $this->expectException(OrganizationNotContactable::class);
        ($this->handler)($this->aCommand());
    }

    public function testRejectsUnknownContact(): void
    {
        $this->organizations->add('org-1');

        $this->expectException(InvalidValue::class);
        ($this->handler)($this->aCommand(contactId: 'c-inconnu'));
    }

    public function testRejectsSecondActiveLeadForSameOrganization(): void
    {
        $this->organizations->add('org-1');
        ($this->handler)($this->aCommand());

        $this->expectException(ActiveLeadAlreadyExists::class);
        ($this->handler)(new CreateLead('lead-2', 'tenant-1', 'org-1', null, 'es>fr', 'DIRECT', 'LOW', 'PUBLISHING'));
    }

    public function testAllowsNewLeadWhenPreviousOneIsTerminal(): void
    {
        $this->organizations->add('org-1');
        ($this->handler)($this->aCommand());
        $lead = $this->leads->get(LeadId::fromString('lead-1'));
        $lead->markLost(new \DateTimeImmutable());
        $this->leads->save($lead);

        ($this->handler)(new CreateLead('lead-2', 'tenant-1', 'org-1', null, 'en>fr', 'REFERRAL', 'MEDIUM', 'PUBLISHING'));

        self::assertSame(PipelineStatus::TO_CONTACT, $this->leads->get(LeadId::fromString('lead-2'))->status());
    }
}
