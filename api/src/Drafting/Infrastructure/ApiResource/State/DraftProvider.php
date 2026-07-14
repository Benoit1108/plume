<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Drafting\Application\Query\GetDraft\GetDraft;
use App\Drafting\Application\Query\ListDrafts\ListDrafts;
use App\Drafting\Application\ReadModel\DraftView;
use App\Drafting\Infrastructure\ApiResource\DraftResource;
use App\Shared\Application\Query\QueryBus;
use App\Shared\Domain\Exception\NotFound;

/**
 * Lecture des brouillons (collection par piste + item) via le QueryBus.
 *
 * @implements ProviderInterface<DraftResource>
 */
final class DraftProvider implements ProviderInterface
{
    public function __construct(private readonly QueryBus $queryBus)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $leadId = $uriVariables['leadId'] ?? null;
            if (!\is_string($leadId)) {
                return [];
            }

            /** @var DraftView[] $views */
            $views = $this->queryBus->ask(new ListDrafts($leadId));

            return array_map(self::toResource(...), $views);
        }

        $id = $uriVariables['id'] ?? null;
        if (!\is_string($id)) {
            return null;
        }

        try {
            /** @var DraftView $view */
            $view = $this->queryBus->ask(new GetDraft($id));
        } catch (NotFound) {
            return null;
        }

        return self::toResource($view);
    }

    public static function toResource(DraftView $view): DraftResource
    {
        $resource = new DraftResource();
        $resource->id = $view->id;
        $resource->leadId = $view->leadId;
        $resource->type = $view->type;
        $resource->targetLanguage = $view->targetLanguage;
        $resource->templateId = $view->templateId;
        $resource->subject = $view->subject;
        $resource->body = $view->body;
        $resource->status = $view->status;
        $resource->failureReason = $view->failureReason;
        $resource->createdAt = $view->createdAt->format(\DateTimeInterface::ATOM);
        $resource->updatedAt = $view->updatedAt->format(\DateTimeInterface::ATOM);

        return $resource;
    }
}
