<?php

declare(strict_types=1);

namespace App\Tests\Drafting\Application;

use App\Drafting\Application\Command\GenerateDraft\GenerateDraft;
use App\Drafting\Application\Command\GenerateDraft\GenerateDraftHandler;
use App\Drafting\Application\LeadContext;
use App\Drafting\Domain\Draft\DraftId;
use App\Drafting\Domain\Draft\DraftStatus;
use App\Drafting\Domain\Draft\Event\DraftRequested;
use App\Drafting\Domain\Draft\Exception\DraftingNotAllowed;
use App\Drafting\Domain\Template\Exception\TemplateNotFound;
use App\Shared\Domain\Exception\InvalidValue;
use App\Tests\Support\FakeLeadGateway;
use App\Tests\Support\FixedClock;
use App\Tests\Support\InMemoryDraftRepository;
use App\Tests\Support\InMemoryTemplateRepository;
use App\Tests\Support\RecordingEventBus;
use PHPUnit\Framework\TestCase;

final class GenerateDraftHandlerTest extends TestCase
{
    private const string TENANT = '0197b7e2-0000-7000-8000-000000000001';

    private InMemoryDraftRepository $drafts;
    private InMemoryTemplateRepository $templates;
    private FakeLeadGateway $leads;
    private RecordingEventBus $eventBus;
    private GenerateDraftHandler $handler;

    protected function setUp(): void
    {
        $this->drafts = new InMemoryDraftRepository();
        $this->templates = new InMemoryTemplateRepository();
        $this->leads = new FakeLeadGateway();
        $this->eventBus = new RecordingEventBus();
        $this->handler = new GenerateDraftHandler(
            $this->drafts,
            $this->templates,
            $this->leads,
            $this->eventBus,
            new FixedClock(new \DateTimeImmutable('2026-07-14 09:00:00')),
        );
    }

    public function testRequestsGenerationAndPublishesDraftRequested(): void
    {
        $this->leads->add(self::TENANT, 'lead-1', $this->context());

        ($this->handler)(new GenerateDraft('draft-1', self::TENANT, 'lead-1', 'APPLICATION_EMAIL', 'fr', null));

        $draft = $this->drafts->get(DraftId::fromString('draft-1'));
        self::assertSame(DraftStatus::GENERATING, $draft->status());
        self::assertSame('lead-1', $draft->leadId());
        self::assertSame(1, $this->eventBus->countOf(DraftRequested::class));
    }

    public function testRejectsUnknownLead(): void
    {
        $this->expectException(InvalidValue::class);
        ($this->handler)(new GenerateDraft('draft-1', self::TENANT, 'ghost', 'APPLICATION_EMAIL', 'fr', null));
    }

    public function testRejectsDoNotContactTarget(): void
    {
        $this->leads->add(self::TENANT, 'lead-1', $this->context(contactAllowed: false));

        $this->expectException(DraftingNotAllowed::class);
        ($this->handler)(new GenerateDraft('draft-1', self::TENANT, 'lead-1', 'APPLICATION_EMAIL', 'fr', null));
    }

    public function testRejectsUnknownTemplate(): void
    {
        $this->leads->add(self::TENANT, 'lead-1', $this->context());

        $this->expectException(TemplateNotFound::class);
        ($this->handler)(new GenerateDraft('draft-1', self::TENANT, 'lead-1', 'APPLICATION_EMAIL', 'fr', 'ghost-template'));
    }

    public function testRejectsUnknownDraftType(): void
    {
        $this->leads->add(self::TENANT, 'lead-1', $this->context());

        $this->expectException(InvalidValue::class);
        ($this->handler)(new GenerateDraft('draft-1', self::TENANT, 'lead-1', 'CARRIER_PIGEON', 'fr', null));
    }

    private function context(bool $contactAllowed = true): LeadContext
    {
        return new LeadContext(
            organizationId: 'org-1',
            organizationName: 'Éditions du Nord',
            segment: 'PUBLISHING',
            languagePair: 'en>fr',
            status: 'TO_CONTACT',
            contactName: 'Jeanne Duval',
            contactAllowed: $contactAllowed,
        );
    }
}
