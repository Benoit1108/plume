<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Prospecting\Infrastructure\ApiResource\State\InteractionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/** Entrée du journal d'interactions (timeline d'une piste) — lecture seule. */
#[ApiResource(
    shortName: 'Interaction',
    normalizationContext: ['groups' => ['interaction:read']],
    operations: [
        new GetCollection(
            uriTemplate: '/leads/{leadId}/interactions',
            uriVariables: ['leadId' => new Link(fromClass: LeadResource::class)],
            provider: InteractionProvider::class,
        ),
    ],
)]
final class InteractionResource
{
    #[Groups(['interaction:read'])]
    public ?string $id = null;

    #[Groups(['interaction:read'])]
    public string $type = '';

    /** @var array<string, mixed> */
    #[Groups(['interaction:read'])]
    public array $payload = [];

    #[Groups(['interaction:read'])]
    public ?string $occurredOn = null;
}
