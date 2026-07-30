<?php

declare(strict_types=1);

namespace App\Directory\Application\Catalog;

/**
 * Annuaire suggéré : catalogue de référence (cibles pré-identifiées) que la traductrice peut ajouter
 * à son Répertoire. Port — la source concrète (fichier JSON aujourd'hui, table/admin demain) est un
 * détail d'infrastructure.
 */
interface DirectoryCatalog
{
    /** @return CatalogEntry[] */
    public function all(): array;

    public function find(string $id): ?CatalogEntry;
}
