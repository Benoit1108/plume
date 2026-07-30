<?php

declare(strict_types=1);

namespace App\Directory\Application\Catalog;

/**
 * Une entrée de l'annuaire suggéré (données de référence, hors tenant) : une cible potentielle que
 * la traductrice peut ajouter à SON Répertoire en un clic. Simple DTO de lecture.
 */
final class CatalogEntry
{
    /**
     * @param string[] $languages
     * @param string[] $segments
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $country,
        public readonly ?string $website,
        public readonly array $languages,
        public readonly array $segments,
        public readonly ?string $note,
    ) {
    }
}
