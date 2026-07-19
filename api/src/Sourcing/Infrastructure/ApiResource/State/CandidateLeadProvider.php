<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Application\Query\QueryBus;
use App\Sourcing\Application\Query\GetCandidateQueue\GetCandidateQueue;
use App\Sourcing\Application\ReadModel\CandidateQueueRow;
use App\Sourcing\Infrastructure\ApiResource\CandidateLeadResource;

/**
 * GET /candidate-leads → file de tri (annonces PENDING du tenant).
 *
 * @implements ProviderInterface<CandidateLeadResource>
 */
final class CandidateLeadProvider implements ProviderInterface
{
    public function __construct(private readonly QueryBus $queryBus)
    {
    }

    /** @return CandidateLeadResource[] */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        /** @var CandidateQueueRow[] $rows */
        $rows = $this->queryBus->ask(new GetCandidateQueue());

        return array_map(self::toResource(...), $rows);
    }

    private static function toResource(CandidateQueueRow $row): CandidateLeadResource
    {
        $resource = new CandidateLeadResource();
        $resource->id = $row->id;
        $resource->source = $row->source;
        $resource->status = $row->status;
        $resource->title = $row->title;
        $resource->organizationName = $row->organizationName;
        $resource->languagePair = $row->languagePair;
        $resource->url = $row->url;
        $resource->excerpt = $row->excerpt;
        $resource->postedAt = $row->postedAt;
        $resource->ingestedAt = $row->ingestedAt;

        return $resource;
    }
}
