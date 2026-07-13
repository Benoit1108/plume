<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Account\Application\Command\UpdateWeeklyGoal\UpdateWeeklyGoal;
use App\Account\Infrastructure\ApiResource\ProfileResource;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;

/**
 * PATCH /profile → UpdateWeeklyGoal. Le tenant vient du contexte (JWT).
 *
 * @implements ProcessorInterface<ProfileResource, ProfileResource>
 */
final class ProfileProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProfileResource
    {
        $tenant = $this->tenantContext->get() ?? throw new \LogicException('No tenant in context.');

        $this->commandBus->dispatch(new UpdateWeeklyGoal($tenant->toString(), $data->weeklyGoal));

        return $data;
    }
}
