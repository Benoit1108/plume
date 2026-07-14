<?php

declare(strict_types=1);

namespace App\Tests\Drafting\Domain;

use App\Drafting\Domain\Draft\Draft;
use App\Drafting\Domain\Draft\DraftId;
use App\Drafting\Domain\Draft\DraftStatus;
use App\Drafting\Domain\Draft\DraftType;
use App\Drafting\Domain\Draft\Event\DraftEdited;
use App\Drafting\Domain\Draft\Event\DraftGenerated;
use App\Drafting\Domain\Draft\Event\DraftRequested;
use App\Drafting\Domain\Draft\Exception\DraftNotEditable;
use App\Drafting\Domain\Draft\Exception\DraftNotGenerating;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

/** Test de domaine pur : cycle de vie draft-first (GENERATING → READY/FAILED). */
final class DraftTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-07-14 10:00:00');
    }

    private function aDraft(): Draft
    {
        return Draft::request(
            DraftId::fromString('draft-1'),
            TenantId::fromString('tenant-1'),
            'lead-1',
            DraftType::APPLICATION_EMAIL,
            LanguageCode::fromString('fr'),
            null,
            $this->now,
        );
    }

    public function testRequestStartsGeneratingWithRichEvent(): void
    {
        $draft = $this->aDraft();

        self::assertSame(DraftStatus::GENERATING, $draft->status());

        $events = $draft->pullDomainEvents();
        self::assertInstanceOf(DraftRequested::class, $events[0]);
        self::assertSame('lead-1', $events[0]->leadId);
        self::assertSame('APPLICATION_EMAIL', $events[0]->type);
        self::assertSame('fr', $events[0]->targetLanguage);
    }

    public function testCompleteMakesItEditable(): void
    {
        $draft = $this->aDraft();
        $draft->pullDomainEvents();

        $draft->complete('Candidature', 'Bonjour…', $this->now);

        self::assertSame(DraftStatus::READY, $draft->status());
        self::assertSame('Candidature', $draft->subject());
        self::assertInstanceOf(DraftGenerated::class, $draft->pullDomainEvents()[0]);

        $draft->edit('Candidature — traduction', 'Bonjour, édité.', $this->now);
        self::assertSame('Bonjour, édité.', $draft->body());
        self::assertInstanceOf(DraftEdited::class, $draft->pullDomainEvents()[0]);
    }

    public function testCannotEditWhileGenerating(): void
    {
        $draft = $this->aDraft();

        $this->expectException(DraftNotEditable::class);
        $draft->edit('X', 'Y', $this->now);
    }

    public function testFailKeepsDisplayableReasonAndAllowsRegenerate(): void
    {
        $draft = $this->aDraft();
        $draft->fail('Le service de génération est indisponible.', $this->now);
        self::assertSame(DraftStatus::FAILED, $draft->status());
        self::assertSame('Le service de génération est indisponible.', $draft->failureReason());
        $draft->pullDomainEvents();

        $draft->regenerate($this->now);

        self::assertSame(DraftStatus::GENERATING, $draft->status());
        self::assertNull($draft->failureReason());
        self::assertInstanceOf(DraftRequested::class, $draft->pullDomainEvents()[0]);
    }

    public function testCannotRegenerateWhileGenerating(): void
    {
        $draft = $this->aDraft();

        $this->expectException(DraftNotEditable::class);
        $draft->regenerate($this->now);
    }

    public function testRedeliveredCompletionNeverOverwritesAReviewedDraft(): void
    {
        // Messenger livre at-least-once : la seconde livraison arrive sur un READY.
        $draft = $this->aDraft();
        $draft->complete('Objet', 'Première génération.', $this->now);
        $draft->edit('Objet relu', 'Corps relu par une humaine.', $this->now);

        try {
            $draft->complete('Objet', 'Seconde génération (redélivrance).', $this->now);
            self::fail('Expected DraftNotGenerating.');
        } catch (DraftNotGenerating) {
        }

        self::assertSame('Corps relu par une humaine.', $draft->body());
        self::assertSame(DraftStatus::READY, $draft->status());
    }

    public function testLateFailureNeverDowngradesAReadyDraft(): void
    {
        $draft = $this->aDraft();
        $draft->complete(null, 'Corps.', $this->now);

        $this->expectException(DraftNotGenerating::class);
        $draft->fail('generation_failed', $this->now);
    }

    public function testRegenerateReopensTheGenerationCycle(): void
    {
        $draft = $this->aDraft();
        $draft->complete(null, 'Corps.', $this->now);
        $draft->regenerate($this->now);

        // Après regenerate, un nouveau complete est légitime.
        $draft->complete(null, 'Nouveau corps.', $this->now);
        self::assertSame('Nouveau corps.', $draft->body());
    }

    public function testEmptyGeneratedBodyIsRejected(): void
    {
        $draft = $this->aDraft();

        $this->expectException(InvalidValue::class);
        $draft->complete(null, '   ', $this->now);
    }
}
