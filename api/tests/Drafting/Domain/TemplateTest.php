<?php

declare(strict_types=1);

namespace App\Tests\Drafting\Domain;

use App\Drafting\Domain\Draft\DraftType;
use App\Drafting\Domain\Template\Event\TemplateCreated;
use App\Drafting\Domain\Template\Event\TemplateUpdated;
use App\Drafting\Domain\Template\Template;
use App\Drafting\Domain\Template\TemplateId;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\Segment;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class TemplateTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-07-14 10:00:00');
    }

    private function aTemplate(): Template
    {
        return Template::create(
            TemplateId::fromString('tpl-1'),
            TenantId::fromString('tenant-1'),
            'Candidature édition',
            DraftType::APPLICATION_EMAIL,
            Segment::PUBLISHING,
            LanguageCode::fromString('fr'),
            'Candidature — {{langues}}',
            'Bonjour {{contact}}, … {{signature}}',
            $this->now,
        );
    }

    public function testCreateTrimsNameAndRecordsEvent(): void
    {
        $template = $this->aTemplate();

        self::assertSame('Candidature édition', $template->name());
        self::assertInstanceOf(TemplateCreated::class, $template->pullDomainEvents()[0]);
    }

    public function testUpdateRecordsEvent(): void
    {
        $template = $this->aTemplate();
        $template->pullDomainEvents();

        $template->update('Relance édition', DraftType::FOLLOW_UP_EMAIL, Segment::PUBLISHING, LanguageCode::fromString('fr'), null, 'Bonjour {{contact}}, je me permets de revenir…', $this->now);

        self::assertSame(DraftType::FOLLOW_UP_EMAIL, $template->type());
        self::assertInstanceOf(TemplateUpdated::class, $template->pullDomainEvents()[0]);
    }

    public function testEmptyBodyIsRejected(): void
    {
        $this->expectException(InvalidValue::class);
        $this->aTemplate()->update('X', DraftType::COVER_LETTER, Segment::OTHER, LanguageCode::fromString('en'), null, '  ', $this->now);
    }
}
