<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Directory\Application\Command\CreateOrganization\CreateOrganization;
use App\Directory\Application\Command\UpdateOrganization\UpdateOrganization;
use App\Directory\Infrastructure\ApiResource\OrganizationResource;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Application\IdGenerator;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;

/**
 * Écriture (POST / PATCH) : délègue au CommandBus. Le tenant vient du contexte (JWT).
 *
 * @implements ProcessorInterface<OrganizationResource, OrganizationResource>
 */
final class OrganizationProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly TenantContext $tenantContext,
        private readonly IdGenerator $ids,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationResource
    {
        if ($operation instanceof Post) {
            $id = $this->ids->generate();
            $this->commandBus->dispatch(new CreateOrganization(
                $id,
                $this->currentTenantId(),
                $data->name,
                $data->type,
                $data->website,
                $data->country,
                $data->workingLanguages,
                $data->segments,
                $data->notes,
            ));
            $data->id = $id;

            return $data;
        }

        $this->commandBus->dispatch(new UpdateOrganization(
            (string) $data->id,
            $data->name,
            $data->type,
            $data->website,
            $data->country,
            $data->workingLanguages,
            $data->segments,
            $data->notes,
            $data->doNotContact,
        ));

        return $data;
    }

    private function currentTenantId(): string
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new \LogicException('No tenant in context.');
        }

        return $tenant->toString();
    }
}
