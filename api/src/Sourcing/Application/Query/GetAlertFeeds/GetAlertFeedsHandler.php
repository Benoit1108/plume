<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Query\GetAlertFeeds;

use App\Shared\Application\Query\QueryHandler;
use App\Sourcing\Application\ReadModel\AlertFeedList;

final class GetAlertFeedsHandler implements QueryHandler
{
    public function __construct(private readonly AlertFeedList $feeds)
    {
    }

    /** @return \App\Sourcing\Application\ReadModel\AlertFeedRow[] */
    public function __invoke(GetAlertFeeds $query): array
    {
        return $this->feeds->all();
    }
}
