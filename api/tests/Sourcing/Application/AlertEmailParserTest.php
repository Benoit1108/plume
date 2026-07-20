<?php

declare(strict_types=1);

namespace App\Tests\Sourcing\Application;

use App\Sourcing\Application\AlertEmail\AlertEmailParser;
use App\Sourcing\Domain\CandidateLead\Source;
use PHPUnit\Framework\TestCase;

final class AlertEmailParserTest extends TestCase
{
    private AlertEmailParser $parser;

    protected function setUp(): void
    {
        $this->parser = new AlertEmailParser();
    }

    public function testProducesOneCandidatePerEmailWithSourceFromSender(): void
    {
        $alerts = $this->parser->parse(
            'jobs-noreply@linkedin.com',
            'Traducteur EN>FR',
            "Une offre : https://example.test/job/42\nLinkedIn",
            'msg-1',
        );

        self::assertCount(1, $alerts);
        self::assertSame('LINKEDIN', $alerts[0]->source);
        self::assertSame('Traducteur EN>FR', $alerts[0]->title);
        self::assertSame('https://example.test/job/42', $alerts[0]->url);
        self::assertSame('msg-1', $alerts[0]->externalId);
        self::assertStringContainsString('Une offre', (string) $alerts[0]->excerpt);
        self::assertNotNull($alerts[0]->rawPayload);
    }

    public function testDetectsKnownSendersAndFallsBackToManual(): void
    {
        self::assertSame(Source::PROZ->value, $this->parser->parse('no-reply@proz.com', 'x', 'b', 'e')[0]->source);
        self::assertSame(Source::TRANSLATORSCAFE->value, $this->parser->parse('a@translatorscafe.com', 'x', 'b', 'e')[0]->source);
        self::assertSame(Source::MANUAL->value, $this->parser->parse('a@inconnu.example', 'x', 'b', 'e')[0]->source);
    }

    public function testEmptySubjectYieldsNothing(): void
    {
        self::assertSame([], $this->parser->parse('a@proz.com', '   ', 'body', 'e'));
    }

    public function testBodyWithoutUrlHasNullUrl(): void
    {
        self::assertNull($this->parser->parse('a@proz.com', 'Titre', 'Pas de lien ici.', 'e')[0]->url);
    }
}
