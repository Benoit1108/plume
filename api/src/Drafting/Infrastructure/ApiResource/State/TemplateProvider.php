<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Drafting\Application\Command\SeedDefaultTemplates\SeedDefaultTemplates;
use App\Drafting\Application\Query\GetTemplate\GetTemplate;
use App\Drafting\Application\Query\ListTemplates\ListTemplates;
use App\Drafting\Application\ReadModel\TemplateView;
use App\Drafting\Infrastructure\ApiResource\TemplateResource;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Application\Query\QueryBus;
use App\Shared\Domain\Exception\NotFound;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;

/**
 * Lecture des gabarits. La collection SEED les 3 gabarits par défaut à la
 * première utilisation (décision M1.4 n°6) — la commande est idempotente.
 *
 * @implements ProviderInterface<TemplateResource>
 */
final class TemplateProvider implements ProviderInterface
{
    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly CommandBus $commandBus,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $tenant = $this->tenantContext->get() ?? throw new \LogicException('No tenant in context.');
            $this->commandBus->dispatch(new SeedDefaultTemplates($tenant->toString()));

            /** @var TemplateView[] $views */
            $views = $this->queryBus->ask(new ListTemplates());

            return array_map(self::toResource(...), $views);
        }

        $id = $uriVariables['id'] ?? null;
        if (!\is_string($id)) {
            return null;
        }

        try {
            /** @var TemplateView $view */
            $view = $this->queryBus->ask(new GetTemplate($id));
        } catch (NotFound) {
            return null;
        }

        return self::toResource($view);
    }

    public static function toResource(TemplateView $view): TemplateResource
    {
        $resource = new TemplateResource();
        $resource->id = $view->id;
        $resource->name = $view->name;
        $resource->type = $view->type;
        $resource->segment = $view->segment;
        $resource->language = $view->language;
        $resource->subject = $view->subject;
        $resource->body = $view->body;

        return $resource;
    }
}
