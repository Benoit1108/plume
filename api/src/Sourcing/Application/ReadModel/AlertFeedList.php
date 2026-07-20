<?php

declare(strict_types=1);

namespace App\Sourcing\Application\ReadModel;

/** Flux configurés du tenant courant (lecture, SQL direct fail-closed). */
interface AlertFeedList
{
    /** @return AlertFeedRow[] */
    public function all(): array;
}
