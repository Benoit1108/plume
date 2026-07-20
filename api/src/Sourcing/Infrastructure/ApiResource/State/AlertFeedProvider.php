<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Application\Query\QueryBus;
use App\Sourcing\Application\Query\GetAlertFeeds\GetAlertFeeds;
use App\Sourcing\Application\ReadModel\AlertFeedRow;
use App\Sourcing\Infrastructure\ApiResource\SourceResource;

/**
 * GET /sources → flux configurés du tenant courant.
 *
 * @implements ProviderInterface<SourceResource>
 */
final class AlertFeedProvider implements ProviderInterface
{
    public function __construct(private readonly QueryBus $queryBus)
    {
    }

    /** @return SourceResource[] */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        /** @var AlertFeedRow[] $rows */
        $rows = $this->queryBus->ask(new GetAlertFeeds());

        return array_map(self::toResource(...), $rows);
    }

    private static function toResource(AlertFeedRow $row): SourceResource
    {
        $resource = new SourceResource();
        $resource->id = $row->id;
        $resource->source = $row->source;
        $resource->url = $row->url;
        $resource->label = $row->label;
        $resource->active = $row->active;
        $resource->createdAt = $row->createdAt;

        return $resource;
    }
}
