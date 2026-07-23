<?php

declare(strict_types=1);

namespace App\Tests\Sourcing\Application;

use App\Sourcing\Application\AlertEmail\AlertEmailParser;
use App\Sourcing\Application\AlertEmail\LinkedInAlertEmailParser;
use App\Sourcing\Domain\CandidateLead\Source;
use PHPUnit\Framework\TestCase;

final class AlertEmailParserTest extends TestCase
{
    private AlertEmailParser $parser;

    protected function setUp(): void
    {
        $this->parser = new AlertEmailParser();
    }

    public function testDelegatesToFineParserWhenItRecognizesTheEmail(): void
    {
        // Avec le parser fin LinkedIn branché, un digest à 2 offres → 2 candidats structurés.
        $dispatcher = new AlertEmailParser([new LinkedInAlertEmailParser()]);
        $body = "Titre 1\nBoîte 1\nParis, France\nVoir l’offre : https://www.linkedin.com/comm/jobs/view/111\n"
            ."-----\nTitre 2\nBoîte 2\nLyon, France\nVoir l’offre : https://www.linkedin.com/comm/jobs/view/222";

        $alerts = $dispatcher->parse('jobalerts-noreply@linkedin.com', 'Alertes', $body, 'msg-1');

        self::assertCount(2, $alerts);
        self::assertSame('Titre 1', $alerts[0]->title);
        self::assertSame('linkedin-111', $alerts[0]->externalId);
    }

    public function testFallsBackToGenericWhenFineParserFindsNothing(): void
    {
        // Email LinkedIn sans lien d'offre : le parser fin rend [] → extraction générique (1/email).
        $dispatcher = new AlertEmailParser([new LinkedInAlertEmailParser()]);
        $alerts = $dispatcher->parse('jobalerts-noreply@linkedin.com', 'Invitation à se connecter', 'Bonjour, https://www.linkedin.com/feed', 'msg-9');

        self::assertCount(1, $alerts);
        self::assertSame(Source::LINKEDIN->value, $alerts[0]->source);
        self::assertSame('Invitation à se connecter', $alerts[0]->title); // titre = sujet (générique)
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
