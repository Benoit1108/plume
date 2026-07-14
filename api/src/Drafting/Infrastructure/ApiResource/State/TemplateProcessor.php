<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Drafting\Application\Command\CreateTemplate\CreateTemplate;
use App\Drafting\Application\Command\DeleteTemplate\DeleteTemplate;
use App\Drafting\Application\Command\UpdateTemplate\UpdateTemplate;
use App\Drafting\Application\Query\GetTemplate\GetTemplate;
use App\Drafting\Application\ReadModel\TemplateView;
use App\Drafting\Infrastructure\ApiResource\TemplateResource;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Application\IdGenerator;
use App\Shared\Application\Query\QueryBus;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;

/**
 * Écritures gabarit : créer, mettre à jour, supprimer.
 *
 * @implements ProcessorInterface<TemplateResource, TemplateResource|null>
 */
final class TemplateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
        private readonly TenantContext $tenantContext,
        private readonly IdGenerator $ids,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?TemplateResource
    {
        if ($operation instanceof HttpOperation && 'DELETE' === $operation->getMethod()) {
            $id = $uriVariables['id'] ?? null;
            if (!\is_string($id)) {
                throw new \LogicException('Missing template id.');
            }
            $this->commandBus->dispatch(new DeleteTemplate($id));

            return null;
        }

        $id = $uriVariables['id'] ?? null;
        if (\is_string($id)) {
            $this->commandBus->dispatch(new UpdateTemplate(
                $id,
                $data->name,
                $data->type,
                $data->segment,
                strtolower($data->language),
                $data->subject,
                $data->body,
            ));
        } else {
            $tenant = $this->tenantContext->require();
            $id = $this->ids->generate();
            $this->commandBus->dispatch(new CreateTemplate(
                $id,
                $tenant->toString(),
                $data->name,
                $data->type,
                $data->segment,
                strtolower($data->language),
                $data->subject,
                $data->body,
            ));
        }

        /** @var TemplateView $view */
        $view = $this->queryBus->ask(new GetTemplate($id));

        return TemplateProvider::toResource($view);
    }
}
