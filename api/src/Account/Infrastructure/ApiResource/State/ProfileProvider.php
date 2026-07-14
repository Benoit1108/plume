<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Account\Application\Query\GetProfile\GetProfile;
use App\Account\Application\ReadModel\ProfileView;
use App\Account\Infrastructure\ApiResource\ProfileResource;
use App\Shared\Application\Query\QueryBus;

/**
 * GET /profile (défauts appliqués si jamais personnalisé).
 *
 * @implements ProviderInterface<ProfileResource>
 */
final class ProfileProvider implements ProviderInterface
{
    public function __construct(private readonly QueryBus $queryBus)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProfileResource
    {
        /** @var ProfileView $view */
        $view = $this->queryBus->ask(new GetProfile());

        $resource = new ProfileResource();
        $resource->weeklyGoal = $view->weeklyGoal;
        $resource->timezone = $view->timezone;
        $resource->bio = $view->bio;
        $resource->specialties = $view->specialties;
        $resource->signature = $view->signature;

        return $resource;
    }
}
