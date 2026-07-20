<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Sourcing\Infrastructure\ApiResource\Input\AlertFeedInput;
use App\Sourcing\Infrastructure\ApiResource\State\AlertFeedProcessor;
use App\Sourcing\Infrastructure\ApiResource\State\AlertFeedProvider;
use App\Sourcing\Infrastructure\ApiResource\State\PollSourcesProcessor;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Sources d'annonces (flux RSS configurés par le tenant, M3.1b) + relève manuelle (M3.1a).
 * API orientée cas d'usage : ajouter / activer / désactiver / retirer un flux, et relever.
 */
#[ApiResource(
    shortName: 'Source',
    normalizationContext: ['groups' => ['source:read']],
    operations: [
        new GetCollection(uriTemplate: '/sources', provider: AlertFeedProvider::class),
        new Post(
            uriTemplate: '/sources',
            name: 'source_add',
            input: AlertFeedInput::class,
            read: false,
            output: false,
            status: 201,
            processor: AlertFeedProcessor::class,
            openapi: new Operation(summary: 'Ajouter un flux d\'annonces (RSS)'),
        ),
        new Post(
            uriTemplate: '/sources/{id}/activate',
            name: 'source_activate',
            input: false,
            read: false,
            output: false,
            status: 204,
            processor: AlertFeedProcessor::class,
            openapi: new Operation(summary: 'Activer un flux'),
        ),
        new Post(
            uriTemplate: '/sources/{id}/deactivate',
            name: 'source_deactivate',
            input: false,
            read: false,
            output: false,
            status: 204,
            processor: AlertFeedProcessor::class,
            openapi: new Operation(summary: 'Désactiver un flux'),
        ),
        new Delete(
            uriTemplate: '/sources/{id}',
            name: 'source_remove',
            read: false,
            output: false,
            status: 204,
            processor: AlertFeedProcessor::class,
            openapi: new Operation(summary: 'Retirer un flux'),
        ),
        new Post(
            uriTemplate: '/sources/poll',
            name: 'sources_poll',
            input: false,
            read: false,
            output: false,
            status: 202,
            processor: PollSourcesProcessor::class,
            openapi: new Operation(summary: 'Relever la source configurée (tenant courant) et ingérer les annonces trouvées'),
        ),
    ],
)]
final class SourceResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['source:read'])]
    public ?string $id = null;

    #[Groups(['source:read'])]
    public string $source = 'RSS';

    #[Groups(['source:read'])]
    public string $url = '';

    #[Groups(['source:read'])]
    public string $label = '';

    #[Groups(['source:read'])]
    public bool $active = true;

    #[Groups(['source:read'])]
    public ?string $createdAt = null;
}
