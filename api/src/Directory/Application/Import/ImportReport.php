<?php

declare(strict_types=1);

namespace App\Directory\Application\Import;

/** Récapitulatif d'un import : compteurs + erreurs par ligne. */
final class ImportReport
{
    public int $imported = 0;
    public int $skipped = 0;
    public int $failed = 0;

    /** @var list<array{line: int, message: string}> */
    public array $errors = [];

    public function addError(int $line, string $message): void
    {
        $this->errors[] = ['line' => $line, 'message' => $message];
    }
}
