<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure;

use App\Shared\Infrastructure\Export\CsvCell;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Injection de formules CSV (revue P3) : une cellule commençant par `= + - @` (ou une tabulation)
 * est exécutée par le tableur de CELUI QUI OUVRE le fichier — l'exploitant pour l'export des
 * comptes, l'utilisatrice pour son export RGPD. On préfixe d'une apostrophe, sans abîmer les
 * valeurs légitimes (nombres, dates, texte ordinaire).
 */
final class CsvCellTest extends TestCase
{
    /** @return iterable<string, array{mixed, string}> */
    public static function cells(): iterable
    {
        yield 'formule' => ['=1+1', "'=1+1"];
        yield 'DDE' => ["=cmd|'/c calc'!A1", "'=cmd|'/c calc'!A1"];
        yield 'plus' => ['+33612345678', "'+33612345678"];
        yield 'moins' => ['-2+3', "'-2+3"];
        yield 'arobase' => ['@SUM(A1)', "'@SUM(A1)"];
        yield 'tabulation' => ["\tvaleur", "'\tvaleur"];
        yield 'email piégé' => ['=x@plume.test', "'=x@plume.test"];
        yield 'texte ordinaire' => ['Éditions du Nord', 'Éditions du Nord'];
        yield 'email ordinaire' => ['marie@plume.test', 'marie@plume.test'];
        yield 'date' => ['2026-08-06 10:00:00', '2026-08-06 10:00:00'];
        yield 'entier' => [42, '42'];
        yield 'entier négatif' => [-42, '-42']; // un NOMBRE ne peut pas être une formule
        yield 'null' => [null, ''];
        yield 'booléen' => [true, '1'];
        yield 'chaîne vide' => ['', ''];
    }

    #[DataProvider('cells')]
    public function testNeutralizesFormulasOnly(mixed $value, string $expected): void
    {
        self::assertSame($expected, CsvCell::safe($value));
    }

    public function testSafeRowMapsEveryCell(): void
    {
        self::assertSame(["'=A1", 'Actes Sud', '3'], CsvCell::safeRow(['=A1', 'Actes Sud', 3]));
    }
}
