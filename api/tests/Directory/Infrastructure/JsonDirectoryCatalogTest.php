<?php

declare(strict_types=1);

namespace App\Tests\Directory\Infrastructure;

use App\Directory\Infrastructure\Catalog\JsonDirectoryCatalog;
use PHPUnit\Framework\TestCase;

/** Lecture de l'annuaire suggéré depuis le JSON livré + tolérance (fichier absent → vide). */
final class JsonDirectoryCatalogTest extends TestCase
{
    private const REAL_FILE = __DIR__.'/../../../data/directory-catalog.json';

    public function testReadsShippedEntries(): void
    {
        $catalog = new JsonDirectoryCatalog(self::REAL_FILE);

        $entries = $catalog->all();
        self::assertNotEmpty($entries);
        foreach ($entries as $entry) {
            self::assertNotSame('', $entry->id);
            self::assertNotSame('', $entry->name);
            self::assertContains($entry->type, ['PUBLISHER', 'AV_STUDIO', 'AGENCY', 'OTHER']);
        }

        $first = $entries[0];
        self::assertSame($first, $catalog->find($first->id));
        self::assertNull($catalog->find('does-not-exist'));
    }

    public function testMissingFileYieldsEmptyCatalog(): void
    {
        self::assertSame([], (new JsonDirectoryCatalog('/nope/absent.json'))->all());
    }
}
