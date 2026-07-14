<?php

declare(strict_types=1);

namespace App\Prospecting\Application\ReadModel;

/** Le « où ça mord » : résultats cumulés par segment (comptes par piste, via le journal). */
final class SegmentStats
{
    public function __construct(
        public readonly string $segment,
        public readonly int $contacted,
        public readonly int $replied,
        public readonly int $won,
    ) {
    }
}
