<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Prospecting\Application\Query\GetToday\GetToday;
use App\Prospecting\Application\ReadModel\LeadView;
use App\Prospecting\Application\ReadModel\TodayView;
use App\Prospecting\Infrastructure\ApiResource\LeadResource;
use App\Prospecting\Infrastructure\ApiResource\TodayResource;
use App\Shared\Application\Query\QueryBus;

/**
 * GET /today — délègue au QueryBus, mappe la vue vers le DTO.
 *
 * @implements ProviderInterface<TodayResource>
 */
final class TodayProvider implements ProviderInterface
{
    public function __construct(private readonly QueryBus $queryBus)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TodayResource
    {
        /** @var TodayView $view */
        $view = $this->queryBus->ask(new GetToday());

        $resource = new TodayResource();
        $resource->followUpsDue = array_map(static fn (LeadView $lead): LeadResource => LeadProvider::toResource($lead), $view->followUpsDue);
        $resource->toContact = array_map(static fn (LeadView $lead): LeadResource => LeadProvider::toResource($lead), $view->toContact);
        $resource->weeklyTarget = $view->weeklyProgress->target;
        $resource->weeklyDone = $view->weeklyProgress->done;
        $resource->streak = $view->weeklyProgress->streak;

        return $resource;
    }
}
