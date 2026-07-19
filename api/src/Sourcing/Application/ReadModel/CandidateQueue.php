<?php

declare(strict_types=1);

namespace App\Sourcing\Application\ReadModel;

/** File de tri (lecture) — annonces en attente, SQL direct fail-closed sur le tenant. */
interface CandidateQueue
{
    /** @return CandidateQueueRow[] */
    public function pending(): array;
}
