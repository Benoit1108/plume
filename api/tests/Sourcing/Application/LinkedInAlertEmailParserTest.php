<?php

declare(strict_types=1);

namespace App\Tests\Sourcing\Application;

use App\Sourcing\Application\AlertEmail\LinkedInAlertEmailParser;
use App\Sourcing\Domain\CandidateLead\Source;
use PHPUnit\Framework\TestCase;

/**
 * Parser fin LinkedIn, sur un ÉCHANTILLON RÉEL (email d'alerte emploi capté sur le compte de
 * test — 2 offres). Vérifie l'extraction structurée par offre + l'URL canonique + le dédoublonnage.
 */
final class LinkedInAlertEmailParserTest extends TestCase
{
    // Corps text/plain réel (jetons de tracking des URLs raccourcis pour le test).
    private const string DIGEST = <<<'TXT'
        Votre alerte Emploi a été créée : Traduction  (France).
        Vous recevrez des notifications lorsque de nouvelles offres d’emploi correspondant à vos préférences de recherche seront publiées.

        RESPONSABLE TRADUCTION GLOBAL - H/F
        Petzl
        Crolles, Auvergne-Rhône-Alpes, France
        Cette entreprise recrute activement
        Voir l’offre d’emploi : https://www.linkedin.com/comm/jobs/view/4441144270?alertAction=markasviewed&midToken=AQH&trk=eml
        ---------------------------------------------------------
        Full Time Senior Linguist English-Vietnamese
        Dr.Localize
        Job, Auvergne-Rhône-Alpes, France
        Voir l’offre d’emploi : https://www.linkedin.com/comm/jobs/view/4442438343?alertAction=markasviewed&midToken=AQH
        ---------------------------------------------------------
        Voir toutes les offres d’emploi :https://www.linkedin.com/comm/jobs/search
        ----------------------------------------
        Cet e-mail est destiné à Plume Dev (Traducteur indépendant chez aucune/none)
        © 2026 LinkedIn Ireland Unlimited Company, Wilton Plaza, Dublin 2.
        TXT;

    private LinkedInAlertEmailParser $parser;

    protected function setUp(): void
    {
        $this->parser = new LinkedInAlertEmailParser();
    }

    public function testSupportsLinkedInSenders(): void
    {
        self::assertTrue($this->parser->supports('jobalerts-noreply@linkedin.com'));
        self::assertFalse($this->parser->supports('no-reply@proz.com'));
    }

    public function testExtractsOneCandidatePerJob(): void
    {
        $alerts = $this->parser->parse('Votre alerte Emploi', self::DIGEST, 'msg-1');

        self::assertCount(2, $alerts);

        self::assertSame(Source::LINKEDIN->value, $alerts[0]->source);
        self::assertSame('RESPONSABLE TRADUCTION GLOBAL - H/F', $alerts[0]->title);
        self::assertSame('Petzl', $alerts[0]->organizationName);
        self::assertSame('Crolles, Auvergne-Rhône-Alpes, France', $alerts[0]->excerpt);
        // URL canonique publique (jetons de tracking du mail écartés).
        self::assertSame('https://www.linkedin.com/jobs/view/4441144270', $alerts[0]->url);
        self::assertSame('linkedin-4441144270', $alerts[0]->externalId);

        self::assertSame('Full Time Senior Linguist English-Vietnamese', $alerts[1]->title);
        self::assertSame('Dr.Localize', $alerts[1]->organizationName);
        self::assertSame('Job, Auvergne-Rhône-Alpes, France', $alerts[1]->excerpt);
        self::assertSame('https://www.linkedin.com/jobs/view/4442438343', $alerts[1]->url);
        self::assertSame('linkedin-4442438343', $alerts[1]->externalId);
    }

    public function testExternalIdIsJobIdSoRepeatsDeduplicate(): void
    {
        // La même offre revue dans un digest ultérieur porte le même externalId → dédoublonnée.
        $a = $this->parser->parse('x', self::DIGEST, 'msg-1');
        $b = $this->parser->parse('x', self::DIGEST, 'msg-2');
        self::assertSame($a[0]->externalId, $b[0]->externalId);
    }

    public function testSameJobTwiceInEmailYieldsOneAlert(): void
    {
        $body = "Titre A\nSociété A\nParis, France\nVoir l’offre : https://www.linkedin.com/comm/jobs/view/999\n"
            ."-----\nAutre bloc\nVoir l’offre : https://www.linkedin.com/comm/jobs/view/999";
        $alerts = $this->parser->parse('x', $body, 'm');
        self::assertCount(1, $alerts);
    }

    public function testNoJobLinksYieldsNothing(): void
    {
        // Email LinkedIn sans lien d'offre (ex. notification réseau) → rien → le générique prendra le relais.
        self::assertSame([], $this->parser->parse('Sujet', "Vous avez une nouvelle relation.\nhttps://www.linkedin.com/feed", 'm'));
    }
}
