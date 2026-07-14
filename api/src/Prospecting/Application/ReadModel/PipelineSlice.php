<?php

declare(strict_types=1);

namespace App\Prospecting\Application\ReadModel;

/** Une tranche de la répartition du pipeline (statut → nombre de pistes). */
final class PipelineSlice
{
    public function __construct(
        public readonly string $status,
        public readonly int $count,
    ) {
    }
}
