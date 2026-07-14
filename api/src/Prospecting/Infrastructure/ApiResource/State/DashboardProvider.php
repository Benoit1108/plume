<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Prospecting\Application\Query\GetDashboard\GetDashboard;
use App\Prospecting\Application\ReadModel\DashboardView;
use App\Prospecting\Application\ReadModel\PipelineSlice;
use App\Prospecting\Application\ReadModel\SegmentStats;
use App\Prospecting\Application\ReadModel\WeekActivity;
use App\Prospecting\Infrastructure\ApiResource\DashboardResource;
use App\Shared\Application\Query\QueryBus;

/**
 * GET /dashboard — délègue au QueryBus, mappe la vue vers le DTO.
 *
 * @implements ProviderInterface<DashboardResource>
 */
final class DashboardProvider implements ProviderInterface
{
    public function __construct(private readonly QueryBus $queryBus)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): DashboardResource
    {
        /** @var DashboardView $view */
        $view = $this->queryBus->ask(new GetDashboard());

        $resource = new DashboardResource();
        $resource->contacted = $view->contacted;
        $resource->replied = $view->replied;
        $resource->won = $view->won;
        $resource->lost = $view->lost;
        $resource->activeLeads = $view->activeLeads;
        $resource->outreachThisMonth = $view->outreachThisMonth;
        $resource->weeklyTarget = $view->weeklyTarget;
        $resource->pipeline = array_map(
            static fn (PipelineSlice $slice): array => ['status' => $slice->status, 'count' => $slice->count],
            $view->pipeline,
        );
        $resource->weeklyActivity = array_map(
            static fn (WeekActivity $week): array => ['weekStart' => $week->weekStart, 'acts' => $week->acts],
            $view->weeklyActivity,
        );
        $resource->segments = array_map(
            static fn (SegmentStats $stats): array => [
                'segment' => $stats->segment,
                'contacted' => $stats->contacted,
                'replied' => $stats->replied,
                'won' => $stats->won,
            ],
            $view->segments,
        );

        return $resource;
    }
}
