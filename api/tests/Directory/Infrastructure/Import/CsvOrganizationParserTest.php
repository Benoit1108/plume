<?php

declare(strict_types=1);

namespace App\Tests\Directory\Infrastructure\Import;

use App\Directory\Infrastructure\Import\CsvOrganizationParser;
use PHPUnit\Framework\TestCase;

/** Test unitaire pur du parser CSV (sans base ni conteneur). */
final class CsvOrganizationParserTest extends TestCase
{
    private CsvOrganizationParser $parser;

    protected function setUp(): void
    {
        $this->parser = new CsvOrganizationParser();
    }

    public function testParsesHeadersAndNormalizesValues(): void
    {
        $csv = <<<CSV
            nom,type,site,pays,langues,segments,notes
            Actes Sud,Éditeur,https://actes-sud.fr,France,en fr,Édition;Technique,à relancer
            CSV;

        $result = $this->parser->parse($csv);

        self::assertCount(1, $result->rows);
        self::assertSame([], $result->errors);

        $row = $result->rows[0];
        self::assertSame('Actes Sud', $row->name);
        self::assertSame('PUBLISHER', $row->type);
        self::assertSame('https://actes-sud.fr', $row->website);
        self::assertNull($row->country, 'un libellé de pays non ISO est abandonné');
        self::assertSame(['en', 'fr'], $row->languages);
        self::assertSame(['PUBLISHING', 'TECHNICAL'], $row->segments);
        self::assertSame('à relancer', $row->notes);
    }

    public function testDetectsSemicolonDelimiterAndParsesContact(): void
    {
        $csv = "nom;pays;contact;email;téléphone\nLa Volte;FR;Marie Dupont;marie@lavolte.net;0102030405";

        $result = $this->parser->parse($csv);

        self::assertCount(1, $result->rows);
        $row = $result->rows[0];
        self::assertSame('FR', $row->country);
        self::assertSame('Marie Dupont', $row->contactName);
        self::assertSame('marie@lavolte.net', $row->contactEmail);
        self::assertSame('0102030405', $row->contactPhone);
    }

    public function testDefaultsUnknownTypeToOtherAndDropsUnknownSegments(): void
    {
        $csv = "nom,type,segments\nEdiLivre,Truc,Cuisine Audiovisuel";

        $row = $this->parser->parse($csv)->rows[0];

        self::assertSame('OTHER', $row->type);
        self::assertSame(['AUDIOVISUAL'], $row->segments);
    }

    public function testReportsRowWithMissingNameWithoutFailingOthers(): void
    {
        $csv = "nom,pays\n,FR\nHachette,FR";

        $result = $this->parser->parse($csv);

        self::assertCount(1, $result->rows);
        self::assertSame('Hachette', $result->rows[0]->name);
        self::assertCount(1, $result->errors);
        self::assertSame(2, $result->errors[0]['line']);
    }

    public function testThrowsWhenFileIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->parser->parse("   \n  ");
    }

    public function testThrowsWhenNameColumnIsMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->parser->parse("pays,type\nFR,Éditeur");
    }
}
