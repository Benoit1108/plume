<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Account\Application\Command\UpdateProfile\UpdateProfile;
use App\Account\Infrastructure\ApiResource\ProfileResource;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;

/**
 * PATCH /profile → UpdateProfile (merge-patch : le provider a chargé l'état
 * courant, seuls les champs envoyés changent). Le tenant vient du JWT.
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
        $tenant = $this->tenantContext->require();

        $this->commandBus->dispatch(new UpdateProfile(
            $tenant->toString(),
            $data->weeklyGoal,
            $data->bio,
            $data->specialties,
            $data->signature,
            $data->firstName,
            $data->lastName,
        ));

        return $data;
    }
}
