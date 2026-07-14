<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Prospecting\Application\Command\CreateLead\CreateLead;
use App\Prospecting\Application\Query\GetLead\GetLead;
use App\Prospecting\Application\ReadModel\LeadView;
use App\Prospecting\Infrastructure\ApiResource\LeadResource;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Application\IdGenerator;
use App\Shared\Application\Query\QueryBus;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;

/**
 * Création d'une piste (POST /leads) → CreateLead. Le tenant vient du contexte (JWT).
 *
 * @implements ProcessorInterface<LeadResource, LeadResource>
 */
final class LeadWriteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
        private readonly TenantContext $tenantContext,
        private readonly IdGenerator $ids,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LeadResource
    {
        $tenant = $this->tenantContext->require();

        $id = $this->ids->generate();
        $this->commandBus->dispatch(new CreateLead(
            $id,
            $tenant->toString(),
            $data->organizationId,
            $data->contactId,
            strtolower($data->languagePair),
            $data->source,
            $data->priority,
            $data->segment,
        ));

        /** @var LeadView $view */
        $view = $this->queryBus->ask(new GetLead($id));

        return LeadProvider::toResource($view);
    }
}
