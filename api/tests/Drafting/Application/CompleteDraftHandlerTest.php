<?php

declare(strict_types=1);

namespace App\Tests\Drafting\Application;

use App\Drafting\Application\Command\CompleteDraft\CompleteDraft;
use App\Drafting\Application\Command\CompleteDraft\CompleteDraftHandler;
use App\Drafting\Application\Command\FailDraft\FailDraft;
use App\Drafting\Application\Command\FailDraft\FailDraftHandler;
use App\Drafting\Domain\Draft\Draft;
use App\Drafting\Domain\Draft\DraftId;
use App\Drafting\Domain\Draft\DraftStatus;
use App\Drafting\Domain\Draft\DraftType;
use App\Drafting\Domain\Draft\Exception\DraftNotFound;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\FixedClock;
use App\Tests\Support\InMemoryDraftRepository;
use App\Tests\Support\RecordingEventBus;
use PHPUnit\Framework\TestCase;

/** Ceinture-bretelles worker : le tenant de la commande doit être celui du brouillon. */
final class CompleteDraftHandlerTest extends TestCase
{
    private InMemoryDraftRepository $drafts;
    private RecordingEventBus $eventBus;
    private FixedClock $clock;

    protected function setUp(): void
    {
        $this->drafts = new InMemoryDraftRepository();
        $this->eventBus = new RecordingEventBus();
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-07-14 10:00:00'));
        $this->drafts->save(Draft::request(
            DraftId::fromString('draft-1'),
            TenantId::fromString('0197b7e2-0000-7000-8000-000000000001'),
            'lead-1',
            DraftType::APPLICATION_EMAIL,
            LanguageCode::fromString('fr'),
            null,
            $this->clock->now(),
        ));
    }

    public function testCompletesForTheOwningTenant(): void
    {
        $handler = new CompleteDraftHandler($this->drafts, $this->eventBus, $this->clock);
        $handler(new CompleteDraft('0197b7e2-0000-7000-8000-000000000001', 'draft-1', 'Objet', 'Corps.'));

        self::assertSame(DraftStatus::READY, $this->drafts->get(DraftId::fromString('draft-1'))->status());
    }

    public function testCompleteWithForeignTenantIsNotFound(): void
    {
        $handler = new CompleteDraftHandler($this->drafts, $this->eventBus, $this->clock);

        $this->expectException(DraftNotFound::class);
        $handler(new CompleteDraft('0197b7e2-0000-7000-8000-00000000dead', 'draft-1', null, 'Corps.'));
    }

    public function testFailWithForeignTenantIsNotFound(): void
    {
        $handler = new FailDraftHandler($this->drafts, $this->eventBus, $this->clock);

        $this->expectException(DraftNotFound::class);
        $handler(new FailDraft('0197b7e2-0000-7000-8000-00000000dead', 'draft-1', 'generation_failed'));
    }
}
