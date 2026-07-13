<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\Import;

use App\Directory\Application\Import\ImportedOrganizationRow;

/** Résultat du parsing : lignes exploitables + erreurs de niveau ligne (ex. nom manquant). */
final class CsvParseResult
{
    /**
     * @param ImportedOrganizationRow[]               $rows
     * @param list<array{line: int, message: string}> $errors
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $errors,
    ) {
    }
}
