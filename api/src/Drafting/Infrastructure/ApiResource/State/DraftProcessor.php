<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Drafting\Application\Command\DeleteDraft\DeleteDraft;
use App\Drafting\Application\Command\EditDraft\EditDraft;
use App\Drafting\Application\Command\GenerateDraft\GenerateDraft;
use App\Drafting\Application\Command\RegenerateDraft\RegenerateDraft;
use App\Drafting\Application\Query\GetDraft\GetDraft;
use App\Drafting\Application\ReadModel\DraftView;
use App\Drafting\Infrastructure\ApiResource\DraftResource;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Application\IdGenerator;
use App\Shared\Application\Query\QueryBus;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;

/**
 * Écritures brouillon : générer (POST sous la piste), éditer (PATCH),
 * régénérer (POST /regenerate), supprimer (DELETE).
 *
 * @implements ProcessorInterface<DraftResource, DraftResource|null>
 */
final class DraftProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
        private readonly TenantContext $tenantContext,
        private readonly IdGenerator $ids,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?DraftResource
    {
        if ($operation instanceof HttpOperation && 'DELETE' === $operation->getMethod()) {
            $id = $this->stringVariable($uriVariables, 'id');
            $this->commandBus->dispatch(new DeleteDraft($id));

            return null;
        }

        if ($operation instanceof Post && isset($uriVariables['leadId'])) {
            // POST /leads/{leadId}/drafts : demande de génération (asynchrone).
            $tenant = $this->tenantContext->require();
            $id = $this->ids->generate();
            $this->commandBus->dispatch(new GenerateDraft(
                $id,
                $tenant->toString(),
                $this->stringVariable($uriVariables, 'leadId'),
                $data->type,
                strtolower($data->targetLanguage),
                $data->templateId,
            ));

            return $this->requery($id);
        }

        $id = $this->stringVariable($uriVariables, 'id');
        if ($operation instanceof Post) {
            // POST /drafts/{id}/regenerate
            $this->commandBus->dispatch(new RegenerateDraft($id));
        } else {
            // PATCH /drafts/{id}
            $this->commandBus->dispatch(new EditDraft($id, $data->subject, $data->body));
        }

        return $this->requery($id);
    }

    private function requery(string $id): DraftResource
    {
        /** @var DraftView $view */
        $view = $this->queryBus->ask(new GetDraft($id));

        return DraftProvider::toResource($view);
    }

    /** @param array<string, mixed> $uriVariables */
    private function stringVariable(array $uriVariables, string $key): string
    {
        $value = $uriVariables[$key] ?? null;

        return \is_string($value) ? $value : throw new \LogicException(sprintf('Missing "%s" in URI.', $key));
    }
}
