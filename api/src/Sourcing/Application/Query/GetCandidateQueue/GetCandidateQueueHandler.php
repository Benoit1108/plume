<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Query\GetCandidateQueue;

use App\Shared\Application\Query\QueryHandler;
use App\Sourcing\Application\ReadModel\CandidateQueue;

final class GetCandidateQueueHandler implements QueryHandler
{
    public function __construct(private readonly CandidateQueue $queue)
    {
    }

    /** @return \App\Sourcing\Application\ReadModel\CandidateQueueRow[] */
    public function __invoke(GetCandidateQueue $query): array
    {
        return $this->queue->pending();
    }
}
